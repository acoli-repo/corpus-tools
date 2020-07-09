<?php
	// Script to edit HTML pages
	// (c) Maarten Janssen, 2015

	check_login();
	check_folder("Pages");
	$id = $_GET['id'];
	$deflang = $settings['languages']['default'] or $deflang = "en";

	$filedescs = array (
		"home" => "Homepage - first page to show upon entry",
		"cqptext" => "Starting page of the search function (action=cqp - subpage without title)",
		"tagsettext" => "Explanation above the tagset (action=tagset - subpage without title)",
		"searchhelp" => "Explanation of the advanced search",
		"rawsearchtext" => "Explantion of the raw text search",
		"breaks" => "Explanation of how TEITOK deals with (line/page) breaks",
		"xmlreqs" => "Explanation of the restrictions TEITOK places on XML files",
		"notfound" => "Page to be shown when looking for a non-existing page",
		"notli" => "Page to be shown when accessing a restricted area while not logged in",
		);
	
	if ( $act == "save" ) {

		$id = $_POST['id'];
		if ( !$id ) {
			$id = $_POST['newid'];
			if ( !$id ) fatal ( "No filename given" );
			if ( substr($id, -5) != ".html" && substr($id, -3) != ".md" ) $id .= ".html";
			if ( file_exists("Pages/$id") )  fatal ( "File $id already exists" );
		};
		if ( substr($id,-5) != ".html" && substr($id, -3) != ".md" ) $id .= ".html";
		$pagename = preg_replace("/\..*$/", "", $id);

		file_put_contents("Pages/$id", $_POST['content']);
		
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=$pagename';</script>
			";
			
	} else if ( $act == "trash" ) {

		$id = $_GET['id'];
		if ( !file_exists( "Pages/$id") ) { fatal("<p>No such HTML or MD page: $id"); };
		
		if ( !file_exists("Trash") ) mkdir("Trash");
		if ( !file_exists("Trash") ) { fatal ("Unable to create Trash folder"); };
		
		rename("Pages/$id", "Trash/$id");		
				
		print "<p>File moved to Trash. Reloading.
			<script language=Javascript>top.location='index.php?action=$action';</script>
			";
			
	} else if ( $id ) {
	
		$ffid = $id;
		$fflang = $_GET['pagelang'] or list ( $ffid, $fflang ) = explode ( "-", $id );
		if ( $id == "new" ) {
		
			$content = "";
			$maintext .= "<h1>Create HTML Page</h1>";
			$idfield = "<p>Filename: <input name=newid value='{$_GET['name']}' size=40>";

			$filename =  $_GET['name']; if ( substr($filename,-5) != '.html' && substr($filename,-3) != '.md' ) $filename .= ".html"; 
		
		} else if ( file_exists("Pages/{$_GET['id']}") ) {
			$filename = $_GET['id'];
			$content = file_get_contents("Pages/{$_GET['id']}");
			$filename =  $_GET['id']; 
			$maintext .= "<h1>Edit HTML Page</h1>
				<h2>Page name: $filename</h2>";

			$idfield = "<input type=hidden name=id value='$filename'>";

		} else {
			if ( !$fflang ) { $fflang = $settings['languages']['default']; }; # hack to force opening non-localized file
			
			$content = getlangfile($ffid, true, $fflang, 'nomd');
			$outfile = str_replace($getlangfile_lastfolder, "Pages", $getlangfile_lastfile);
			$outname = str_replace("Pages/", "", $outfile);
			$newfile = "<p style='color: red;'><i>New file, will be created upon saving</i>";
			$filename = $outname;
			if ( file_exists($outfile) ) {$id = $ffid; $newfile = ""; }
			else if ( $getlangfile_lastfolder == "$ttroot/common/" ) $newfile .= " - pre-filled with content from $getlangfile_lastfolder";
			else if ( $filename != "Pages/$id.html" ) $newfile .= " - pre-filled with content from $getlangfile_lastfile";

			if ( file_exists("Pages/$id.html") && !is_writable("Pages/$id.html") ) {
				fatal ("Due to file permissions, $id.html cannot be edited, please contact the server administrator");
			};
			
			$outfile = str_replace($getlangfile_lastfolder, "Pages", $getlangfile_lastfile);
			$outname = str_replace("Pages/", "", $outfile);
			$maintext .= "<h1>Edit HTML Page</h1>
				<h2>Page name: $outname</h2>$newfile";

			$idfield = "<input type=hidden name=id value='$outname'>";
		};

		# Protect the i18n inside the content to avoid internationalization
		$content = str_replace("%", "&percnt;", $content);

		if ( $id != "new" ) {
			if ( $filedescs[$ffid] ) $maintext .= "<p><i>{$filedescs[$ffid]}</i> $fflang $ffid $deflang $getlangfile_lastfile"; 
		} else if ( preg_match("/-([^.]+)/", $_GET['name'], $matches ) ) { 
			$fflang = $matches[1];
		} else $fflang = $_GET['pagelang'];
		
		$sep = "";
		foreach ( $settings['languages']['options'] as $key => $langset ) {
			$display = $langset['name'] or $display = $key; 
			if ( $key == $fflang ) {
				$othertxt .= "$sep<b><u>$display</u></b>";
				$sep = " &bull; ";
			} else if ( !file_exists("Pages/$ffid-$key.html") ) {
				$othertxt .= "$sep<a href='index.php?action=$action&id=$ffid&pagelang=$key' title='missing' style='color: red; font-weight: bold;'>$display</a>";
				$sep = " &bull; ";
			} else {
				$othertxt .= "$sep<a href='index.php?action=$action&id=$ffid&pagelang=$key' title='existing' style='color: blue; font-weight: bold;'>$display</a>";
				$sep = " &bull; ";
			};
		};
		$maintext .= "<p>Interface languages: $othertxt";
		
		if ( substr($filename,-3) == ".md" ) {
			$protcontent = htmlentities($content, ENT_QUOTES, 'UTF-8');
			$maintext .= "
				<style>
					#mdgroup th { background-color: #eeeeee; color: #666666; }
					#mdgroup th[selected] { background-color: #666666; color: white; }
				</style>
				
				<form action='index.php?action=$action&act=save' id=frm name=frm method=post>
				$idfield
				<table width=100% id='mdgroup'>
				<tr id='buttonrow'>
					<th target='editorrow' selected='1' onclick='viewsel(this)'>Editor</th>
					<!-- <th target='coderow' onclick='viewsel(this)'>Code</th> -->
					<th target='previewrow' onclick='viewsel(this)'>Preview</th>
				</tr>
				<tr style='display: table-row;' id='editorrow'><td colspan=3>
				<div id=\"editor\" style='width: 100%; height: 400px; color: white;'>".$protcontent."</div>
				</td></tr>
				<tr style='display: none;' id='coderow'><td colspan=3>
				<textarea style='width: 100%; height: 400px;' name=content></textarea>
				<tr style='display: none;' id='previewrow'><td colspan=3>
				<div id=\"mdpreview\" style='width: 100%; height: 400px;'></div>
				</td></tr></table>
				<p><input type=button value=Save onClick=\"return runsubmit();\"> $switch
				</form>
				
				<script src=\"$aceurl\" type=\"text/javascript\" charset=\"utf-8\"></script>
				<script language=Javascript src='https://cdnjs.cloudflare.com/ajax/libs/showdown/1.9.0/showdown.min.js'></script>
				<script>
					converter = new showdown.Converter();
					var editor = ace.edit(\"editor\");
					editor.setTheme(\"ace/theme/chrome\");
					editor.getSession().setMode(\"ace/mode/markdown\");
					editor.getSession().on('change', function() {
  						update()
					});	
					update();
					function update() {
						var rawmd = editor.getSession().getValue();
						document.frm.content.value = rawmd;

						document.getElementById('mdpreview').innerHTML = converter.makeHtml(rawmd);
					};
					function runsubmit ( ) {
						document.frm.submit();
					};
					document.getElementById('editor').style['color'] = '#000000';
					
					function viewsel(elm) {
						const views = document.getElementById('buttonrow').getElementsByTagName('th');
						for (let view of views ) {
							var target = view.getAttribute('target');
							if ( view == elm ) {
								document.getElementById(target).style.display = 'table-row';
								view.setAttribute('selected', '1');
							} else {
								document.getElementById(target).style.display = 'none';
								view.removeAttribute('selected');
							};
						};
					};
				</script>

				";

		} else {
			$maintext .= "<script type=\"text/javascript\" src=\"$tinymceurl\"></script>";
			$maintext .= '<script type="text/javascript">
				tinymce.init({
					selector: "textarea",
					convert_urls: false,
					setup: function (ed) {
						ed.on("change", function () {
							onupdate();
						})},
					plugins: [
						 "advlist autolink link image lists charmap print preview hr anchor pagebreak spellchecker",
						 "searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
						 "save table contextmenu directionality emoticons template paste textcolor"
				   ],
					content_css: "Resources/htmlstyles.css", 
					toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | l      ink image | print preview media fullpage | forecolor backcolor ", 
					width: "100%",
					height: 400
				 });
				 var changed = false;
				 window.onbeforeunload = function () {
					if ( changed ) return \'Your XML has been changed, unsaved changes will be lost.\';
				 };
				 function onupdate () {
					changed = true;
				 };
				</script>';
			$maintext .= "
				<p><form action='index.php?action=$action&act=save' method=post>
				$idfield
				<textarea name=content onChange='onupdate'>$content</textarea>
				<p><input type=submit value=Save onClick=\"changed = false;\"> <a href='index.php?action=$action&act=trash&id=$id'>move to trash</a>
				</form>
				";
		};
					

		// Add a session logout tester
		$maintext .= "<script language=Javascript src='$jsurl/sessionrenew.js'></script>";
		
		
	} else {
		$maintext .= "<h1>HTML Pages</h1>
			<p>Select the HTLM page you want to edit, or create a new page.	Remember that 
				in TEITOK, pages end in .html, the home page is called home.thml, and pages localized 
				in a specific language have the language code 
				after a hyphen, as in: home-pt.html.</p>
			
			<hr>
			<p><a href='index.php?action=$action&id=new'>new page</a>
			<hr>
			<table><tr><td><th>Filename<th colspan=2>Description
			";
			
		$files = scandir("Pages");
		sort($files);
		
		foreach ( $files as $entry ) {
			if ( substr($entry,0,1) == "." ) continue;
			$ffn = preg_replace ( "/\.html/", "", $entry );
			list ( $ffid, $fflang ) = explode ("-", $ffn);
			list ( $efid, $eflang ) = explode ("-", $entry);
			$desctxt = $filedescs[$ffid];
			if ( !$desctxt && $menuitems[$efid] ) $desctxt = "<i>".$menuitems[$efid]."</i>";
			if ( !$desctxt ) $desctxt = "<i>Custom page</i>";
			$donepags[$ffid][$fflang] = 1;
			if ( $fflang ) $desctxt .= "<td>for language $fflang";
			$previewlink = preg_replace("/\..*/", "", $entry);
			if ( substr($entry, -5) == ".html" || substr($entry, -3) == ".md" ) $maintext .= "<tr><td><a href='index.php?action=$previewlink'>preview</a> 
					&bull; <a href='index.php?action=$action&id=$ffn'>edit</a><td>$entry<td>$desctxt";
		};
		$maintext .= "</table>";

		# Now - see if there are pages that still need a translation
		foreach ( $donepags as $ffid => $tmp ) {
			if ( is_array($settings['languages']['options']) )
			foreach ( $settings['languages']['options'] as $key => $item ) {
				$ktxt = $item['name'] or $ktxt = $key;
				# if ( $key == $deflang ) { $key = ""; };
				if ( !$tmp[$key] && ($key != $deflang || !$tmp[""]) && $ffid && $ktxt != $deflang ) {
					if ( $filedescs[$ffid] ) $dtxt = " ({$filedescs[$ffid]})"; else $dtxt = "";
					$mistrans .= "<li><a href='index.php?action=$action&id=$ffid-$key'>Add translation into $ktxt for <i>$ffid.html</i></a>$dtxt";
				};
			};
		};
		if ( $mistrans ) {
			$maintext .= "<h2>Create missing translated pages</h2><ul>$mistrans</ul>";
		};

		# Now - see if there are any central TEITOK files that could be used
		foreach ( $filedescs as $key => $val ) {
			if ( !$donepags[$key] ) {
				$mispag .= "<li><a href='index.php?action=$action&id=$key-$lang'>{$val}</a>";
			}; 
		};
		if ( $mispag ) {
			$maintext .= "<h2>Customize standard pages</h2><ul>$mispag</u>";
		};
		
	};
	
?>