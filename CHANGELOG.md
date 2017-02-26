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
* Made te "TEITOK" statement in the menu less prominent
* Trash folder is now created when it does not exist
* Removed the now redundant warning if the sum of dtok/@form does not match tok/@form
* Splitting token by adding &lt;/tok&gt;&lt;tok&gt; now works correctly
* Turned on Facsimile image uploading by default
* Corrected several errors in the C++ modules (require recompilation!)

## [Version 1.1](https://gitlab.com/maartenes/TEITOK/tags/v1.1) (Aug 31, 2016) 

### Improvements 

* `word` is no longer used as a crucial attribute in the CQP corpus 

## [Version 1.0](https://gitlab.com/maartenes/TEITOK/tags/v1.0) (Jul 22, 2016) 

### Improvements 

* Tokens in the CQP results are now clickable, allowing editing errors directly from the search results


## [Version 0.9](https://gitlab.com/maartenes/TEITOK/tags/v0.9) (Jun 6, 2016) 

### Improvements 



## [Version 0.8](https://gitlab.com/maartenes/TEITOK/tags/v0.8) (Apr 29, 2016) 

### Improvements 



## [Version 0.7](https://gitlab.com/maartenes/TEITOK/tags/v0.7) (Mar 15, 2016) 

### Improvements 



## [Version 0.6](https://gitlab.com/maartenes/TEITOK/tags/v0.6) (Dec 15, 2015) 

### Improvements 



## [Version 0.5](https://gitlab.com/maartenes/TEITOK/tags/v0.5) (Nov 9, 2015) 

### Improvements 



## [Version 0.4](https://gitlab.com/maartenes/TEITOK/tags/v0.4) (Nov 9, 2015) 

* TEITOK is now available as a GitLab repository!


