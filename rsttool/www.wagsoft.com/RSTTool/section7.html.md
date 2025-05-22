 

# 7. Printing and Saving Diagrams {#printing-and-saving-diagrams align="center"}

 

This section explains:

1.  How to print your diagrams on the printer.
2.  How to export your RST diagrams in various graphical formats.
3.  How to incorporate these saved files into wordprocessing packages
    such as Latex or MS Word..
4.  How to share analyses with your colleagues.

------------------------------------------------------------------------

## Printing Diagrams

At present, printing support in Tcl/Tk is primitive. All we can do is
save the diagram as a postscript file and try to send this file to the
printer. This is only possible in some cases:

-   **Unix/Linux**: Printing should not be a problem. Just click on the
    \"Print Canvas\" button (in Structure mode) to send the diagram to
    the printer.
-   **Windows**: The \"Print Canvas\" button will only work if you are
    using Tcl/Tk 8.0, 8.1 or 8.2. For those with the latest versions
    (8.3x or 8.4x), the dll (dynamic load library) for printing from
    Tcl/Tk doesn\'t exist yet. I am still waiting for it to appear.\
    In some cases, users report that the printed diagram comes out too
    small to be of use (\"postage stamp size\" according to Bill Mann).
    I think this is a problem with the type of printer, or the printer
    driver, but I don\'t know.
-   **Macintosh**: Currently, the \"Print Canvas\" button does not
    function on Macintoshes. Maybe in the future.

If the \"Print Canvas\" button works for you, the program should send
the whole structure directly to the printer. if the diagram is larger
than a single page, it will be printed over several pages.

If the \"Print Canvas\" button doesn\'t work for you, there are several
solutions:

1.  **Downgrade Tcl/Tk**: If you are a Windows user, uninstall Tcl/Tk
    and install version 8.2, which supports printing. See the Tcl/Tk
    website mentioned in section 2.
2.  **Indirect Printing**: Otherwise, you might try saving the diagram
    as a postscript file (hitting the \"Save PS\" button). You can then
    print this file (see below). Note however, that the structure is
    written to the PS file as a single page, so structures spanning
    multiple pages cannot be printed in this way. I am still looking for
    a solution to this problem.
3.  **Saving in other format and printing from application**: as
    described below, you can save the diagram as a PDX diagram, which
    MayuraDraw can deal with. Using Mayura (Windows only), you can then
    re-export the diagram as either WMF (windows meta file), PDF, EPS,
    AI (Adobe Illustrator), etc. You may have an application which can
    read in one of these formats, and deal with it intelligently (i.e.,
    multi-page printing). I don\'t know of one yet, but I don\'t own
    commercial software.\
    Note that for printing a single-page diagram, exporting as PDF and
    printing that is a good option for all platforms.

------------------------------------------------------------------------

## Exporting RST diagrams in various graphical formats

As mentioned above, RSTTool allows you to export diagrams in two formats
(PS and PDX), and one of these (PDX) can in turn be saved as a number of
other formats (Windows only at present).

### Saving PS Files

When you click on the \"Save PS\" button, you will be presented with a
dialog asking you to \"click on the top node of the RST tree to save\".
Hit the \"OK\" button, and the cursor changes to a \"T\". Click on a
node of the RST Structure and it, and all its dependent structure, will
be saved to a file as postscript. You will be asked for a place to save
the file.

The PS file can be sent to a printer (under Unix: \"lpr \<filename\>\").
Alternatively, it can be inserted into Latex or Microsoft Word (perhaps
other word processing packages also) and printed as part of that
document. See below for details.

Some people report that the PS file saved by RSTTool is truncated to the
right. However, I don\'t think the files are truncated, rather,
Ghostview (a viewer for postscript files) only shows the first print
page of the diagram. If you resize the page larger, you can see more of
the diagram.

However, I still don\'t know how to print these files over multiple
pages.

### Saving PDX Files

When you click on the \"Save PDX\" button, you will be asked if you wish
to save the entire canvas (\"All\") or just the subtree under a
particular node. If you select the first, you will then be asked where
to save the file. If you select the subtree approach, you will be asked
to click on a node, and then the file selection dialog will appear. Once
saved, the PDX file can be loaded using Mayura Draw (obtain from
<http://www.mayura.com>). Using this package you can:

-   Edit the diagram to your taste.
-   Export the diagram to other more useful formats (for inclusion in MS
    Word for instance).

Longer analyses seem to be truncated once loaded into Mayura, but this
is a bug in the display, rather than in the file. If you export the
diagram and insert into MS Word (see below) you can see the whole
diagram.

See the \"Export\" option under the \"File\" menu. The useful options
here are: WMF and EPS (for inclusion in MS Word documents, PDF (for
distribution to other people), and maybe AI (Adobe Illustrator).

**Telling PDX which font to use**: Font names used in PDX files are
different than those used in Tcl/Tk. To get round this problem, there is
a file in Library/FONT_MAP which states which PDX font to use for which
TCL font.

1.  Finding out the Tcl/Tk name of your font: Select \"Appearance
    Options\" from the \"Options\" menu. Your Current font name will be
    displayed, e.g., \"MS Sans Serif\".\

2.  Finding out the name of the font in PDX: Open Mayura Draw. Select
    \"Font\...\" from the \"Text\" menu, and you will see a list of font
    names. Select one, and copy the name to the clipboard.\

3.  Open the RstTool folder: Library, and edit the file \"FONT_MAP\".
    add a line like the ones already there, e.g.,

    MS Sans Serif: TimesNewRomanPSMT

    Save the file.

The new font should be used in your pdx files from then on.

------------------------------------------------------------------------

## Including Diagrams in your Documents

Once you have your diagram saved as a file, you can include it in other
documents, for instance, academic papers.

-   **Latex**: To include a document in latex, saving as a ps file
    should be adequate. These files can using the epsfig package. If you
    are on Windows, you can also try saving as PDX, and using Mayura to
    save as EPS.
-   **Microsoft Word**: Two formats exported by Mayura Draw can be
    inserted into MS Word documents (and perhaps other wordprocessing
    packages):
    -   **Encapsulated Postscript (EPS)**: this format can be included
        in Microsoft Word documents, depending on the version. For
        instance, under Office 98, select \"Picture\...\" from the
        \"Insert\" menu. and select the file. Note that eps or ps files
        cannot be inserted in older versions of MS Word. If inserted,
        EPS graphics may not be displayed in the document properly, but
        will print ok on a postscript printer. EPS won\'t print properly
        on a non-postscript printer. If it doesn\'t work for you, try
        the WMF format (below).\
    -   **Windows Meta File (WMF)**: this format is more robust (works
        on more versions of MS Word, and on non-postscript printers),
        but the picture quality is slightly worse in the printing.
        Insert in the same way as explained for EPS just above.
-   **The Web**: The best way to get RST diagrams onto your web pages is
    to: Save the diagram as pdx format. Load it into Mayura, and
    \"Export\" as JPG. Ensure that Smoothing is Maximal or Normal, and
    Quality is \"High\". This produces a JPG version of your entire
    structure which is of good readable quality.
    The alternative is to do a screen dump (capture the screen as a
    bitmap), trim off parts of the image you don\'t want, and save as a
    gif or jpg. However, if your diagram doesn\'t display in a single
    page, you will need to join the separate screen captures together.
    Messy.\
-   **Drawing Packages**: The AI format exported by Mayura can be loaded
    into CorelDraw, Micrografx Windows Draw, and possibly other
    programs.
    -   **Unix/Linux**: various programs exist to capture a window
        either to a file or to the clipboard. On Unix, I use Snapshot,
        which allows me to select a region of the screen, and then use
        XV to save the resulting file as jpg or gif. Other solutions
        exist.\
    -   **Windows**: Bring RSTTool to the front, with the structure you
        want captured clearly displayed. Press the \"Alt\" key at the
        same time as the \"Print-Scrn\" key, and the top window will be
        saved in the clip board. Then Open your favourite graphic editor
        (I use Paintshop Pro) and paste the diagram into a new screen.
        Then trim out parts of the window you don\'t want, and save the
        diagram as jpg or gif.\
    -   **Macintosh**: There are tools on the Mac to do the same as in
        the above. I am no longer a Mac user, so don\'t know their
        names.\

------------------------------------------------------------------------

## Sharing Analyses with other people

The RS2 format (RSTTool\'s native format) is easily distributable, but
they need the program.

I think the best option here is PDF: Mayura exports in this format,
which is readily viewed on all platforms, with Adobe Acrobat Reader
(available free from
[Adobe](http://www.adobe.com/products/acrobat/readstep.html) for all
platforms). The clarity is very good.

