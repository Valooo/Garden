<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Mark O'Sullivan
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Mark O'Sullivan at mark [at] lussumo [dot] com
*/

class GardenController extends Gdn_Controller {
   
   public function __construct() {
      parent::__construct();
   }
   
   public function Initialize() {
      if ($this->DeliveryType() == DELIVERY_TYPE_ALL) {
         $this->Head = new HeadModule($this);
         $this->Head->AddScript('js/library/jquery.js');
         $this->Head->AddScript('js/library/jquery.livequery.js');
         $this->Head->AddScript('js/library/jquery.form.js');
         $this->Head->AddScript('js/library/jquery.popup.js');
         $this->Head->AddScript('js/library/jquery.menu.js');
         $this->Head->AddScript('js/library/jquery.gardenhandleajaxform.js');
         $this->Head->AddScript('js/global.js');
      }
      
      $this->AddCssFile('default.css');
      $this->AddCssFile('menu.css');
      $this->AddCssFile('popup.css');
      $this->AddCssFile('form.css');
      $this->AddModule('PoweredByVanillaModule');
      
      parent::Initialize();
   }
   
   public function AddSideMenu($CurrentUrl) {
      // Only add to the assets if this is not a view-only request
      if ($this->_DeliveryType == DELIVERY_TYPE_ALL) {
         $SideMenu = new Gdn_MenuModule($this);
         $SideMenu->HtmlId = '';
         $SideMenu->CssClass = 'SideMenu';
         $this->EventArguments['SideMenu'] = &$SideMenu;
         $this->FireEvent('GetAppSettingsMenuItems');
         $this->AddModule($SideMenu, 'Panel');
      }
   }
}