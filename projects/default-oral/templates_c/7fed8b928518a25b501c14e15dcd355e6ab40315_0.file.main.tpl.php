<?php
/* Smarty version 3.1.34-dev-7, created on 2019-02-11 12:20:26
  from '/Library/WebServer/Documents/teitok/default-oral/templates/main.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '3.1.34-dev-7',
  'unifunc' => 'content_5c61688a9a6903_83893905',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7fed8b928518a25b501c14e15dcd355e6ab40315' => 
    array (
      0 => '/Library/WebServer/Documents/teitok/default-oral/templates/main.tpl',
      1 => 1546983833,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_5c61688a9a6903_83893905 (Smarty_Internal_Template $_smarty_tpl) {
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html  style='height: 100%; width: 100%; padding: 0; margin: 0;'><head>
  <meta http-equiv="content-type" content="text/html; charset=UTF-8">
  <link rel="stylesheet" type="text/css" href="http://www.teitok.org/Scripts/teitok.css" media="screen">
  <link rel="stylesheet" type="text/css" href="Resources/htmlstyles.css" media="screen">
  <link rel="stylesheet" type="text/css" href="Resources/xmlstyles.css" media="screen">
  <title><?php echo $_smarty_tpl->tpl_vars['title']->value;?>
</title>
</head>
<body style='height: 100%; width: 100%; padding: 0; margin: 0;'>

	<table style='height: 100%; width: 100%;'>
	<tr><td valign=top style="width: 150px; text-align: center; border-right: 1px solid #999999;">
        	<center><span style='font-size: 24pt;'><?php echo $_smarty_tpl->tpl_vars['title']->value;?>
</center><br>
        	<?php echo $_smarty_tpl->tpl_vars['menu']->value;?>

     <td valign=top style="background-color: white; padding: 20px;">
		<?php echo $_smarty_tpl->tpl_vars['maintext']->value;?>

        </td>
	</tr>
	</table>


</body></html><?php }
}
