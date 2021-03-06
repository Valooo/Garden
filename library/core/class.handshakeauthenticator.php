<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Mark O'Sullivan
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Mark O'Sullivan at mark [at] lussumo [dot] com
*/

/**
 * Validates sessions by handshaking with another site that can share cookies with this site.
 * In order to use this authentication method you need to set the following values in your config.php file:
 * <ul>
 *  <li><b>Garden.Authenticator.Type</b>: Handshake</li>
 *  <li><b>Garden.Authenticator.AuthenticateUrl</b>: A url that will return information about the currently signed on user.
 *  Here is an example:
 *  UniqueID = 1234
 *  Name = SomeUser
 *  DateOfBirth: 1980-10-13
 *  Gender: Male
 *  </li>
 *  <li><b>Garden.Authenticator.Encoding</b>: The encoding of the authenticate url.
 *   Valid values are 'ini' and 'json'</li>
 *  <li><b>Garden.Authenticator.SignInUrl</b>: The url that a user uses to sign into the system.
 *   A %s in this url will be replaced with a url that can be redirected to after the sign out.</li>
 *  <li><b>Garden.Authenticator.SignOutUrl</b>: The url that will sign a user out of the other system.
 *   A %s in this url will be replaced with a url that can be redirected to after the sign out.</li>
 *  <li><b>Garden.Authenticator.RegisterUrl</b>: The url that a user can use to register for a new account.
 *   This url is optional if users cannot self-register for an account.
 *   A %s in this url will be replaced with a url that can be redirected to after the sign out.</li>
 * </ul>
 *
 * @package Garden
 */

class Gdn_HandshakeAuthenticator implements Gdn_IAuthenticator {
	const SignedIn = 1;
	const SignedOut = -1;
	const HandshakeError = -2;
	
	/// Properties ///
	
	public $AuthenticateUrl;
	
	public $Encoding;
	
	private $_HandshakeData;
	
	private $_Identity;
	
	private $_RegisterUrl;
	
	private $_SignInUrl;
	
	private $_SignOutUrl;
	
	/// Constructor ///
	
	public function __construct($Config) {
		if(is_string($Config))
			$Config = Gdn::Config($Config);
			
		$this->AuthenticateUrl = ArrayValue('AuthenticateUrl', $Config);
		$this->_RegisterUrl = ArrayValue('RegisterUrl', $Config);
		$this->_SignInUrl = ArrayValue('SignInUrl', $Config);
		$this->_SignOutUrl = ArrayValue('SignOutUrl', $Config);
		$this->Encoding = ArrayValue('Encoding', $Config, 'ini');
		
		$this->_Identity = Gdn::Factory('Identity');
		$this->_Identity->Init();
	}
	
	/// Methods ///
   public function Authenticate($Data) {
		if(array_key_exists('UserID', $Data)) {
			$this->_Identity->SetIdentity($Data['UserID']);
		}
	}

   public function DeAuthenticate() {
		$this->_Identity->SetIdentity(NULL);
		$Url = sprintf($this->SignOutUrl(), urlencode(Url('/', TRUE)));
		Redirect($Url);
	}
	
	public function GetHandshakeData() {
		if(is_array($this->_HandshakeData))
			return $this->_HandshakeData;
		
		$UrlParts = parse_url($this->AuthenticateUrl);
		$Host = $UrlParts['host'];
		$Port = ArrayValue('port', $UrlParts, '80');
		$Path = $UrlParts['path'];
		$Referer = Gdn_Url::WebRoot(TRUE);
		
		// Make a request to the authenticated Url to see if we are logged in.
		$Pointer = @fsockopen($Host, $Port, $ErrorNumber, $Error);
		
		if (!$Pointer)
			throw new Exception(sprintf(Gdn::Translate('Encountered an error when attempting to authenticate handshake (%1$s): [%2$s] %3$s'), $this->AuthenticateUrl, $ErrorNumber, $Error));
			
		// Get the cookie.
		$Cookie = '';
		foreach($_COOKIE as $Key => $Value) {
			if(strncasecmp($Key, 'XDEBUG', 6) == 0)
				continue;
			
			if(strlen($Cookie) > 0)
				$Cookie .= '; ';
				
			$Cookie .= $Key.'='.urlencode($Value);
		}
		if(strlen($Cookie) > 0)
			$Cookie = "Cookie: $Cookie\r\n";
			
		// Send the necessary headers to get the file
		fputs($Pointer, "GET $Path HTTP/1.0\r\n" .
			"Host: $Host\r\n" .
			"User-Agent: Vanilla/1.0\r\n" .
			"Accept: */*\r\n" .
			//"Accept-Language: ".$Locale."\r\n" .
			"Accept-Charset: utf-8;\r\n" .
			"Keep-Alive: 300\r\n" .
			"Connection: keep-alive\r\n" .
			"Referer: $Referer\r\n" .
			$Cookie .
			"\r\n");

		// Retrieve the response from the remote server
		$Response = '';
		$InBody = FALSE;
		while ($Line = fread($Pointer, 4096)) {
			$Response .= $Line;
		}
		fclose($Pointer);
		// Remove response headers
		$Response = trim(substr($Response, strpos($Response, "\r\n\r\n") + 4));
		
		switch($this->Encoding) {
			case 'json':
				$Result = json_decode($Response, TRUE);
				break;
			case 'ini':
			default:
				$Result = parse_ini_string($Response);
				break;
		}
		$this->_HandshakeData = $Result;
		return $Result;
	}

   public function GetIdentity($ForceHandshake = FALSE) {
		// Check to see if the identity has us logged in.
		if($ForceHandshake === FALSE) {
			$Id = $this->_Identity->GetIdentity();
			if($Id > 0)
				return $Id;
			elseif($Id < 0)
				return 0; // prevent session from grabbing constantly
		}
		
		// Get the handshake data to authenticate.
		$Data = $this->GetHandshakeData();
		if(!array_key_exists('UniqueID', $Data)) {
			// There was some problem getting the userID.
			// The user was probably signed out on the handshake system.
			$this->_Identity->SetIdentity(self::SignedOut, FALSE);
			return 0;
		}
		
		$UserModel = Gdn::UserModel();
		$UserID = $UserModel->Synchronize($Data['UniqueID'], $Data);
		if($UserID)
			$this->_Identity->SetIdentity($UserID, FALSE);
		else
			$this->_Identity->SetIdentity(self::HandshakeError, FALSE);
		
		return $UserID;
	}
	
	public function RegisterUrl($Redirect = '/') {
		$Url = sprintf($this->_RegisterUrl, urlencode(Url($Redirect, TRUE)));
		return $Url;
	}
	
	public function RemoteSignInUrl($Redirect = '/') {
		$Url = sprintf($this->_SignInUrl, urlencode(Url($Redirect, TRUE)));
		return $Url;
	}
	
	public function SetIdentity($Value) {
		$this->_Identity->SetIdentity($Value);
	}
	
	public function SignInUrl($Redirect = '/') {
		return '/entry/handshake/?Target='.urlencode($Redirect);
	}
	
	public function SignOutUrl() {
		$Url = sprintf($this->_SignOutUrl, urlencode(Gdn_Url::Request()));
		return Gdn::Config('Garden.Authenticator.SignOutUrl');
	}
	
	public function State() {
		$Id = $this->_Identity->GetIdentity();
		
		switch($Id) {
			case 0:
			case self::SignedOut:
				if(is_array($this->_HandshakeData) && array_key_exists('UniqueID', $this->_HandshakeData)) {
					$this->SetIdentity(self::HandshakeError);
					return self::HandshakeError;
				}
				return self::SignedOut;
			case self::HandshakeError:
				return self::HandshakeError;
			default:
				return self::SignedIn;
		}
	}
}