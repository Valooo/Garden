<?php if (!defined('APPLICATION')) exit();

// Make sure to force this form to post to the correct place in case the view is
// rendered within another view (ie. /garden/entry/index/):
echo $this->Form->Open(array('Action' => Url('/entry/passwordrequest'), 'id' => 'Form_User_Password'));
?>
<h1><?php echo Translate("Reset my password") ?></h1>
<?php echo $this->Form->Errors(); ?>
<ul>
   <li>
      <?php
         echo $this->Form->Label('Email', 'Email');
         echo $this->Form->TextBox('Email');
      ?>
   </li>
   <li>
      <?php
         echo $this->Form->Button('Request a new password');
      ?>
   </li>
</ul>
<?php echo $this->Form->Close(); ?>