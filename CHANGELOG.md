# Change Log

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
* Removed the now redundant warning if the sum of dtok/@form does not match tok/@form
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

### Bug fixes


## [Version 0.6](https://gitlab.com/maartenes/TEITOK/tags/v0.6) (Dec 15, 2015) 

### Improvements 

### Bug fixes


## [Version 0.5](https://gitlab.com/maartenes/TEITOK/tags/v0.5) (Nov 9, 2015) 

### Improvements 

### Bug fixes


## [Version 0.4](https://gitlab.com/maartenes/TEITOK/tags/v0.4) (Nov 9, 2015) 

* TEITOK is now available as a GitLab repository!


