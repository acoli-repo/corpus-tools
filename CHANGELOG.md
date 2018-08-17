# Change Log

## [Version 2.3](https://gitlab.com/maartenes/TEITOK/tags/v2.3) (Aug 6, 2018)

### Improvements 

* Added the option to compare searches on the map as pie charts
* Added a session-timeout prevention module to keep the session alive during large edits
* Added the option visualize comparisons between named CQL searches
* Added in-document search using CQL
* Added a script to lookup geocoordinates in OSM
* You can now add pages in the page-by-page transcription

### Bug fixes

* Corrected an error preventing private facsimile images from being hidden
* The admin module now shows whether you are running the latest version of TEITOK
* Added internationalization to some missing items
* Made the nest button work in CQP edit
* Added support for RTL scripts in several modules

## [Version 2.2](https://gitlab.com/maartenes/TEITOK/tags/v2.2) (Jul 7, 2018)

### Improvements 

* Added marker clustering to the map view
* Added CQL search to map view
* Added the option store and compare CQL searches

### Bug fixes

* Corrected several errors in the data visualization module


## [Version 2.1](https://gitlab.com/maartenes/TEITOK/tags/v2.1) (Jun 11, 2018)

### Improvements 

* Moved from a single XML visualization to a customizable visualization with various views
* Switched map view from Google Maps to OpenStreetMaps
* Added the pageflow file view
* Added a statistical overview to the data visualization
* Added the wavesurfer visualization for time-aligned audio-based files
* Added a user-based annotation module
* Added the option to have visitor-login using ORCID
* Added a beta version of a tool to convert sound files
* Added a module to search for words in word-aligned facsimile images
* Added a word-sketch module
* Introduced a drag-and-drop file upload system
* Introduced a beta version of a collation system
* Added several options to the dependency tree module

### Bug fixes

* Roll-over tags now work correctly when no tags have been defined
* Missing visualization elements are now created programmatically
* Updated the admin settings module to include recently introduced options
* The tokenization script can now introduce sentence boundaries
* Prevented multi-edit to change potentially misaligned tokens
* Solved some issues in the page-by-page transcription module
* Improved the TT-CQP based data visualization
* Improved the XML reader
* Updated the Source installation instruction and loosened the dependency on Boost in favour of C++11
* Solved several bugs in TT-CQP

## [Version 1.11](https://gitlab.com/maartenes/TEITOK/tags/v1.11) (Jan 15, 2018)

### Improvements 

* Introduced TT-CQP, a custom version of CQP
* Added various additional views to the data visualization
* Added the option to upload a modified CSV to the csv module

### Bug fixes

* TT-CWB-ENCODE now writes .pos files for dependent positions


## [Version 1.10](https://gitlab.com/maartenes/TEITOK/tags/v1.10) (Jan 8, 2018)

### Improvements 

* Added a data visualization module showing statistics and graphs

### Bug fixes

* Improved checksettings (after copying/moving a project)
* Solved a bug in CQP search when searches included a '
* Solved a bug in tt-cwb-encode that sometimes made token content disappear


## [Version 1.9](https://gitlab.com/maartenes/TEITOK/tags/v1.9) (Jan 2, 2018)

### Improvements 

* Moved Javscript libraries out of the repository in favour of CDN
* Added various option to pagetrans (fullscreen, 2up, zoom, simplify, line and token edit)
* Added region edit to place lines and other regions interactively on a facsimile image
* Added the option to use another view than text view (file) for context in CQP search
* Added facsimile view, showing a searchable-PDF style view focussed on the Facsimile image
* Line-by-line editing in pagetrans now allows you to adjust the facsimile cutout

### Bug fixes

* Improved rendering of pb to mimick the treatment of lb
* Added the option to have gtok element for words split across a linebreak
* Made multichange run in the background
* Several improvements to pdf2tei
* Made fatal error display message via a file rather than the URL
* backups folder now created if it does not exist
* Added search result highlight to line view
* Lineview now uses CSS and Javascript to get the cutout images
* Added a warning when merging tokens fails (string replacement failure)
* tokmerge now deals correctly with bboxes
* pdf2tei can now write a logfile
* Added paged view to ttxml
* Added the option to include non-tokenized texts with tt-cwb-encode


## [Version 1.8](https://gitlab.com/maartenes/TEITOK/tags/v1.8) (Dec 5, 2017)

### Improvements 

* Added the option to have an automatic simplified layer, for instance to keep the long s only at pform
* Added a check to see the folder did not get moved or copied, checking the settings if it did
* Added XML validity check before submitting raw XML, avoiding loss of content
* Greatly improved the options for creating new XML files
* Added a module to create an XML files based upon a PDF file
* Improved support for using milestones in XML files
* Added page-by-page transcription using a pre-TEI format comparable to hOCR or PAGESXML for easy transcription
* Made a central recqp script avoiding the need to have project-specific script to create the CQP corpus
* Added a module to help create a template
* Added a list of standard frequency distribution options

### Bug fixes

* Corrected an issue that made line numbers appear various time by redesigning the lb rendering
* Added some missing features to adminsettings.xml
* Corrected the position of the help button in advanced search
* Removed deprecated modules from the repository
* Added i18n for Catalan
* Added a help item to the admin menu
* Corrected an error in the PSDX module which made it not show all results
* Made the upload module a bit more informative
* Made the XML reader work even if there are no records yet


## [Version 1.7](https://gitlab.com/maartenes/TEITOK/tags/v1.7) (Nov 17, 2017)

### Improvements 

* Complete redesign of not-logged-in message system
* Added a tagset for the universal dependencies for dependency trees
* Major overhaul of the options to create a new XML file, including WYSIWYG editing
* Major overhaul of the XML reader, which can now be used as a full module to display simple XML databases

### Bug fixes

* Fixed several bugs in the dependency tree display
* Greatly improved stand-off annotation module
* Removed the non-working option to keep facsimile image on page on scroll
* Made tokview listed to the noshow option in the settings
* Removed recqp from the admin module (which had long been deprecated)
* Removed the (too slow) option to have PHP look for Smarty if it cannot be found
* Removed the namespace in several places where it made the system crash
* Made sure users always get a short ID


## [Version 1.6](https://gitlab.com/maartenes/TEITOK/tags/v1.6) (Jun 08, 2017)

### Improvements 

* Added edit options to deptree
* Complete redesign of annations for stand-off annotation

### Bug fixes

* Fixed a new bug that incorrectly flattened XML
* Fixed issues in the new xmlreader


## [Version 1.5](https://gitlab.com/maartenes/TEITOK/tags/v1.5) (Jun 01, 2017)

### Improvements 

* Facsimile images are now prevented from scrolling out of the window untill the page ends
* Permissions can now have groups, allowing some groups only access to specific functions
* In the CQP distribution you can now indicate which sattributes to include
* Added support for videos for corpora with time-aligned audio
* "Not logged in" messages can now be customized
* Values in PSDX can now have multi-select
* tokedit now show facsimile cut-out when there is a `@bbox` on the token allowing to see OCR/transcription errors quickly
* Added xmlreader, allowing for an easy interface to a spreadsheet-like XML database


### Bug fixes

* New items in the settings can now be required to have a name
* dtokfill can now have anything as its POS tag
* Fixed some bugs that prevented locally stored CQP repositories from properly working
* Prevented facsimile cut-outs in lineview to become too large
* Fixed some bugs wrt filenames that include space, and made new files no longer accept spaces
* Improved search for PSDX
* Improved checklist in myproject
* Improved treatment of dtoks in neotagxml
* sattributes in tt-cwb-encode can now refer to external XML files


# Change Log

## [Version 1.4](https://gitlab.com/maartenes/TEITOK/tags/v1.4) (Feb 26, 2017)

### Improvements 

* The common TEITOK folder no longer needs to be next to the project folder, but can now be loaded from anywhere (default is still one level up)
* dtokmake.pl and dtokfill.pl can now start from any feature, not just form `@pos`

### Bug fixes

* Attributes in the token-popup are now named nodes to be able to style them with CSS
* Made storing the registry in the project folder the default for CQP but with a back-out to the central repository for backward compatibility
* Improved sort order of select pull downs in cqpraw
* Made it possible to have three-letter languages codes in the URL
* Additional smarty variables can now be loaded to the template
* Corrected an error that made orgfile not always show the file
* Made the checklist in myproject look for files on the server better
* The C++ modules now can display a version number to allow checking compatibility between the tagset and the tagger

## [Version 1.3](https://gitlab.com/maartenes/TEITOK/tags/v1.3) (Nov 20, 2016)

### Improvements 

* It is now possible to clip sound files in time-aligned audio files, while keeping the alignment intact 
* Improved dtokmake.pl - you can now indicate which tag is used to detect splits (by default `pos`)
* It is now possible to define additional smarty variables to allow putting information in customized parts of the HTML

### Bug fixes

* Corrected an error that prevented the audio buttons in CQP results to work properly
* Prevented the incorrect "there are no XML files yet" when there are only subfolders
* Minor changes to CSS styling in several parts: menu, tokinfo, cqp
* Minor corrections to csv2tei.pl

## [Version 1.2](https://gitlab.com/maartenes/TEITOK/tags/v1.2) (Nov 6, 2016) 

### Improvements 

* It is now possible to add `morph` tags to the tokens in the XML files providing a morphological analysis; 
when present, this will allow viewing the text in an Interlinear Glossed Text (IGT) format 
* Added a script dtokfill.pl that can fill `dtok` without a `@form` deducing the form from tag + lemma using the lexicon
* It is now possible to store the CQP corpus elsewhere apart from the cqp folder - mostly to enable having multiple corpora in subfolders 
of the cqp folder
* It is now possible to view the raw teiHeader (for admin users)
* Complete rewrite of XPath based find to become much more usable
* The "Main Menu" title can now be customised
* Improved the NeoTag integration in TEITOK
* Nodes in PDSX trees can now be deleted
* You can now have multiple tagsets in the same project (mostly for multilingual corpora)

### Bug fixes

* Added more options to the adminsettings.xml
* Improved dtokmake.pl
* Corrected an error in xmltokenize.pl that made it get into an infinite loop at times
* Improved used a text-direction in CQP results
* Corrected some lingering uses of `word`
* Improved use of regular expressions in multi-edit
* Made the "TEITOK" statement in the menu less prominent
* Trash folder is now created when it does not exist
* Removed the now redundant warning if the sum of `dtok/@form` does not match `tok/@form`
* Splitting token by adding &lt;/tok&gt;&lt;tok&gt; now works correctly
* Turned on Facsimile image uploading by default
* Corrected several errors in the C++ modules (require recompilation!)

## [Version 1.1](https://gitlab.com/maartenes/TEITOK/tags/v1.1) (Aug 31, 2016) 

### Improvements 

* Instead of using the required `word` attribute in CQP as a renaming of `form`, `word` is now a largely redundant attribute, and `form` shows
as such in CQP just like all other attributes leading to less confusion; it is also possible to indicate which form attribute is search by default
when no form is explicitly indicated
* In multiedit, you can now select to not change all search results in the same way, but rather to show an HTML table in which each result
can be edited individually; it it even possible to use a regex transformation to pre-change all results in a systematic way  
* Added a script dtokmake.pl that allows creating dtoks in an easier way: by putting multiple POS in the `@pos` tag separated by a +, 
the corresponding dtoks are automatically created by this scripts; this allows creating dtoks in verticalized views and multiedit mode.
* Removed the option to regenerate the script to create the CQP file, since it leads to undesired results when accidentally clicked
* There is now a TEITOK interface to the csv2tei and tei2csv scripts, which allow you to create a CSV of a specific field in all XML file, change
them in one go and save them back to their individual XML files; this allow you to work with metadata that are distributed over the XML files
as if they were in an Excell table

### Bug fixes

* Corrected an error in rollover visualisation in XML files
* Corrected an error that made some tokens not link to tokedit correctly	
* Made tei2csv.pl more reliable
* Added links to the PSDX trees from with the XML view
* Made the visualisation of the output of scripts better
* Made it impossible to edit (d)tokens without an ID - which used to look like it worked, but did not
* Turned off the option to edit the pform in verticalised view, since it killed the XML inside

## [Version 1.0](https://gitlab.com/maartenes/TEITOK/tags/v1.0) (Jul 22, 2016) 

### Improvements 

* Tokens in the CQP results are now clickable, allowing editing errors directly from the search results
* Dependency relation can now be visualised
* With a position-based tagset, the tags can now be shown in long-form, showing the analysis instead of the tag; and the analysis can be 
shown in various languages based on the selected languages (provided the tagset.xml contains the translations)
* You can now create mtoks from within the interface (by selecting the last token of the mtok and create one to the left)
* Made the selection of the parameters from within neotag work better
* The index can now not only show chapters, but milestones of any type
* sentedit can now be used to edit other types of entities as well (such as verse lines)
* Added the option to check whether all tags used in the corpus are valid according to the tagset
* mtoks now also show on rollover
* Added the option to use an external lexicon in neotagxml (requires recompilation of neotagxml.cpp)

### Bug fixes

* Corrected various errors that did not make mtoks show correctly everywhere
* Corrected an error that made the first facsimile image not show when showing a chapter

## [Version 0.9](https://gitlab.com/maartenes/TEITOK/tags/v0.9) (Jun 6, 2016) 

### Improvements 

* Added bounding boxes, allowing to align parts of the transcription to part of a facsimile image
* Added a global teitok.css that defines some default visualizations
* dtoks are now shown on rollover in the file view
* Added a configuration check to check whether the setup of TEITOK is correct (and safe) - both to myproject and to admin
* Added a lineview mode that shows lines of the transription next to parts of the facsimile image (using the bboxes)
* orgfile can now show the original of an XML file in multiple formats
* XML files are now backed up when saving via ttxml
* The neotag C++ module now deals with form inheritance (recompilation required)

### Bug fixes

* Completed parts of the adminsettings.xml
* Added two scripts csv2tei.pl and tei2csv.pl that import and export from the various XML files into a CSV file allowing to easily modify all teiHeaders 
of all the XML files
* Parsing errors in xmlrenumber.pl are now displayed
* Corrected a bug in the annotations module
* Multiedit queries can now be set to run in the background, preventing timeouts for large files or huge numbers of files
* Replaced split by explode, which is no longer supported in newer PHP versions
* Made it possible to have the smarty class in various locations
* Added comments to the PHP source
* Language switch is no longer shown if there is only one interface language
* Corrected an error when saving sentences
* Updated the installation instructions for src
* Corrected some errors in the skeleton myproject
* Improved display of 2-up images

## [Version 0.8](https://gitlab.com/maartenes/TEITOK/tags/v0.8) (Apr 29, 2016) 

### Improvements 

* It is now possible to use only a part of the facsimile image for a pb - mostly for 2-up images
* There now is a GUI to the settings.xml, with a adminsettings.xml file that lists and describes all possible settings
* Added a script multichange.pl that can be used to run multiple changes in the background
* As an admin user you can now go back after an error occurs (in case you correct the error outside of the interface)
* Added highlight that allows highlighting all result of a CQP search on a single text  directly in the XML file
* Annotation files are now linked from the bottom of the XML display
* Added a module to display the progress of background processes
* Added the option to show results of PSDX searches as text rather than as trees
* CQP registry folder can now be located elsewhere
* Added tokview that shows detailed information about a single token on a page
* Added a search to xdxf

### Bug fixes

* Removed incorrected colours in geomap when there is a maximum of 1 file per location
* Corrected some errors in the standoff module
* Facsimile images located elsewhere are now treated correctly
* Corrected a Javascript error when buttons that were supposed to be present were not there
* Removed several non-finished Perl and PHP scripts from the repository
* xmlrenumber now also numbers verse lines
* Text search in CQP is no longer shown if there are no textual attributes, to allow "corpora" consisting of files only (images, sounds, etc.) and no text
* Corrected some errors in paged display
* Minor improvement to geomap
* Improved display of the context in mergetoks
* Improved display of the tagset, now using a dedicated tttags class
* Corrected a display error with empty tags in the XML (interpreted as start tags by the DOM)
* Corrected an error that made it impossible to save sentences
* Added context restrictions to ttxml
* Several bug fixes the C++ modules

## [Version 0.7](https://gitlab.com/maartenes/TEITOK/tags/v0.7) (Mar 15, 2016) 

### Improvements 

* Added geomap which allows visualising geocoordinates of XML files onto Google maps
* Added the Ace and TinyMCE to the repository to avoid version conflicts
* Added the Javascript modules to the repository
* It is now possible to see all backup files
* Added a GUI module to edit teiHeader templates	

### Bug fixes

* Minor corrections to the CQP search
* Added cqpraw as the pre-XIDX search in case XIDX is not installed
* Creating a new XML file no longer depends on having a template
* Improvements to the C++ modules

## [Version 0.6](https://gitlab.com/maartenes/TEITOK/tags/v0.6) (Dec 15, 2015) 

### Improvements 

* Added a configuration check to the admin module
* Added the option to have the Javascripts in a customised location, allowing for serving them locally rather than pulling them for teitok.corpuswiki.org
* PSDX files are now linked from within the XML display
* Added the option to move pages and XML files to the Trash
* Added CQP-based metadata search to PSDX
* Added sentedit allowing sentence-level tags to be edited in TEITOK
* Added myproject - a skeleton project to start with
* Added an src folder containing C++ programs (NeoTag and CQP index) to the repository

### Bug fixes

* Denied access to the common folder (.htaccess) for safety
* Minor corrections to xmltokenize.pl
* Added a check to see whether XML is valid before editing in several modules
* Cleaned out redundant code from CQP search with XIDX
* Made it possible to have smarty in various locations
* Removed the XQuery option from PSDX since it was not fully worked out
* Now shows the max file upload size in upload
* Lowered the error reporting level since otherwise logs gets stuffed with warnings

## [Version 0.5](https://gitlab.com/maartenes/TEITOK/tags/v0.5) (Nov 9, 2015) 

### Improvements 

* Added several folders to the repository: common pages, scripts, resources, etc.

### Bug fixes


## [Version 0.4](https://gitlab.com/maartenes/TEITOK/tags/v0.4) (Nov 9, 2015) 

* TEITOK is now available as a GitLab repository!


