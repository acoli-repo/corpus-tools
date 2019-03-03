<?php
/* Smarty version 3.1.34-dev-7, created on 2019-02-26 17:21:32
  from '/Users/mjanssen/Git/TEITOK/default-oral/templates/main.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '3.1.34-dev-7',
  'unifunc' => 'content_5c75759c147ba8_36644243',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '2fb1d8a5df12f4a4ebb91065f2bd5716b39798be' => 
    array (
      0 => '/Users/mjanssen/Git/TEITOK/default-oral/templates/main.tpl',
      1 => 1551201680,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_5c75759c147ba8_36644243 (Smarty_Internal_Template $_smarty_tpl) {
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html style='height: 100%; padding: 0; margin: 0;'><head>
  <meta http-equiv="content-type" content="text/html; charset=UTF-8">
  <meta name="description" content="description">
  <meta name="keywords" content="keywords"> 
  <meta name="author" content="author">
  <meta name="robots" content="noindex,nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href='https://fonts.googleapis.com/css?family=Lato' rel='stylesheet'>
  <link rel="stylesheet" type="text/css" href="Resources/xmlstyles.css" media="screen">
  <link rel="stylesheet" type="text/css" href="Resources/htmlstyles.css" media="screen">
  <link rel="shortcut icon" href="favicon.ico">
  <title><?php echo $_smarty_tpl->tpl_vars['title']->value;?>
</title>
</head>
<body style='height: 100%; padding: 0; margin: 0;'>
	<div style='position: fixed; padding: 0; margin: 0; left: 0; top: 0; width: 100%; height: 100%; z-index: 1; opacity: 0.7;'>&nbsp;</div>
	<div style="position: fixed; background-color: white; margin-left: 180px; width: 100%; height: 100%; opacity: 0.95; z-index: 2;">&nbsp;</div>	

	<div style="width: 180px; height: 100%; text-align: center; border-right: 0.5px solid #cc6600; position: fixed; top: 0px; left: 0px; z-index: 100;" id=menu>
        	<div style='font-size: 36px; font-weight: bold; padding-top: 10px; padding-bottom: 0px; text-align: center;'><?php echo $_smarty_tpl->tpl_vars['title']->value;?>
</div>
        	<div style='font-size: 18px; padding-top: 0px; padding-bottom: 10px; text-align: center;'><?php echo $_smarty_tpl->tpl_vars['langtxt']->value;?>
</div>
			<?php echo $_smarty_tpl->tpl_vars['menu']->value;?>

	</div>

     <div id="main" style=" min-width: 800px; position: absolute; top: 0px; padding-top: 5px;  padding-right: 15px; padding-left: 200px; padding-bottom: 50px; height: 100%; z-index: 50; opacity: 1; ">
		<?php echo $_smarty_tpl->tpl_vars['maintext']->value;?>

	</div>


</body></html>
<?php }
}
