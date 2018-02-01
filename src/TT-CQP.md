# TT-CQP

TT-CQP is a custom version of the Corpus Query Processor from the [Corpus WorkBench](http://cwb.sourceforge.net/), written for 
the [TEITOK](http://www.teitok.org/) corpus environment, but which can be used independently of it as well; for the sake of 
compatibility, TT-CQP uses the same files as CQP, meaning you can use TT-CQP to search any
existing indexed CQP corpus. However, it was designed to be used together with TT-CWB-ENCODE, which
is a likewise custom version of CWB-ENCODE, which writes a couple of files that CWB-ENCODE does not;
because of that, some of the options of TT-CQP are not available unless the corpus was made using 
TT-CWB-ENCODE.

TT-CQP is not meant as a replacement for CQP - it implements various of the functions of CQP, while
adding some that CQP does not, but it is not a full re-implementation of CQP, nor is it intended to
become one, although more options will be added over time. Given the overlap between
CQP and TT-CQP, a full idea about how to use TT-CQP can be obtained by consulting the
manual of CQP, together with this document highlighting the differences.

### Options not (yet) implemented

This is an incomplete list of all the options from CQP left out in TT-CQP, some to be potentially 
implemented later, but many not intended to be implemented.

* diacritics insensitive search not yet supported (%d)
* interactive mode - TT-CQP is meant for a piped architecture and only emulates cqp -pi
* all option related to the interactive mode are hence also unsupported (set, show, dump, cat, count, discard, save)
* macros, distance, distabs will not be implemented
* groupings and boolean operators are unlikely to be implemented
* randomize sort should be done in post-processing tools
* reduce is not likely to be implemented
* structural attributes will not be implemented (&lt;s&gt;, /region, expand) - but the can be used as global constraints (`:: match.year = "1990"`)
* aligned corpora will not be supported
* it is not possible to cut lists - you can tabulate a part, or cut externally
* sort does not do reverse order (but does descending order)
* subcorpora cannot be modified by subset, intersection, join, or difference; those options are left for after the pipe

## Added options

### Tabulate

The tabulate command in TT-CQP works the same as in CQP, so to get a list of the fields word and lemma for a query
named Matches, you use `tabulate Matches match.word match.lemma`, where the part before the dot indicates the corpus
position, and the part after the dot the data to be retrieved for that corpus position. For both parts, TT-CQP adds
some functions that are not present in CQP:

* `match.substr(pos,0,1)` will give only the first letter of the pos attribute, which is useful for instance to get the
main pos in position-based tagsets
* `a.word` will give the word attribute for a position named "a" in the query; contrary to CQP, these names are kept, which 
effectively means that `target:[]` is synonymous to `@[]`. Reserved names (match, matchend, keyword, target) take preference over
named positions.
* `head.word` will render the word of the corpus position marked as the head of the match; 
to get the head of any other named token, use `head(a).word`. This 
feature relies on a file `head.corpus.pos`, which matches corpus positions to the related position for the
pattribute head (head can be any
pattribute, but this feature is intended for dependency relations). Named positions take preference over related
positions.
* context shifts can be used with any of the position types, so `adj[1].lemma` will give the lemma first position to the
right of a named position "adj", and `head(target)[-2].word` will give the second word to the left of the head of the target.

### Grouping

Grouping in TT-CQP works slightly different from the way it works in CQP, and the group command more closely 
resembles the tabulate command; where CQP uses `group A match word by match lemma`, TT-CQP uses 
`group A match.word match.lemma`; the reason for this difference is to allow the additional tabulate options to 
be used in grouping, so you can group by `match[-1].substr(pos,0,1)`, `a[1].lemma`, or `head.word`; and where the
CQP command effectively only allows you to group by pairs (word+lemma in the example), the TT-CQP format more
explicitly uses tuples of arbitrary size. For better compatibility with CQP, the CQP format of the group command
will be translated into the TT-CQP format.

### Sorting

Contrary to CQP, in TT-CQP you can sort the results on anything, and not only on pattributes, so
`sort A match.text_year` will sort the results in A on the year of the text (for match), 
`sort A match[1]..word[5].word` will sort on the first 5 words of the right context, 
and
`sort A head[1].substr(pos,0,1) descending` will sort in descending order by the first letter of the part-of-speech 
tag of the first token to the right of the head of the match (who doesn't want to sort on that?). 

### Statistics

TT-CQP allows you to get statistical data for named queries. The format is `stats A lemma`, which will give a statistical
breakdown of the lemma attribute of match (or target) in the CQL query named A. The default output is collocation
with mutual information scores, but it is also possible to give keyword scores. You can use more than one column for the 
statistics, which will be treated as tuples, and options for the statistics can be given after a :: - so `stats A word lemma :: type:collocations`.

The collocation scoring `stats A lemma` looks up the lemma for each collocate of the 
match (or target when specified) in the query A and counts them. It then proceeds to look up the total frequency of that lemma in the subcorpus for A (the corpus
restricted by global contraints, so with `A = ["casa"] :: text_lang="PT"` it would only count the occurrences in 
texts in the Portuguese language), and calculate the expected frequency, and the Chi-Square and Mutual information scores.
The output columns (when not using XML output) are: [lemma, count(lemma), totcount(lemma), expected freq, chi2, mutinf]. 
You can modify the output with the following options:

* `measure:mutinf` will only produce the mutual information score (or chi2 score)
* `context:+3` will use the first three tokens for the right of match/target for counting the collocates
* `context:head` will not use the the position to the left/right of match/target but rather the position of the relative position head (see tabulate)
* `show:lemma` will show the lemma for match/target before the lemma of the collocate, this is especially useful 
to show the deps (dependency relation) when counting by headword.

Keyword scores can be selected by using `stats A lemma :: type:keywords`.  

### XML and JSON output

When using the option --output=xml or --output=json, TT-CQP will produce the output of the group, stats, and tabulate command in XML 
or JSON format, where each tab of the output is marked with its key. An example of an XML and JSON output is given below:

```xml
user> echo 'Matches = [word="casa"] [pos="A.*"]; tabulate Matches match.text_lang match.id match[-5]..match[-1].word match..matchend.word matchend[1]..matchend[5].word match.substr(pos,0,1);' | tt-cqp --output=xml
<results cql="[word=&quot;casa&quot;] [pos=&quot;A.*&quot;]" tab="match.text_lang match.id match[-5]..match[-1].word match..matchend.word matchend[1]..matchend[5].word match.substr(pos,0,1)" size="6">
	<result>
		<tab key="match.text_lang" val="ES" />
		<tab key="match.id" val="w-27" />
		<tab key="match[-5]..match[-1].word" val="de hayer estube en la" />
		<tab key="match..matchend.word" val="casa corresponsal" />
		<tab key="matchend[1]..matchend[5].word" val="de Almeida y me dijo" />
		<tab key="match.substr(pos,0,1)" val="NCFS000N" />
	</result>
	<result>
	...
</results>
```

```
user> echo 'Matches = [word="casa"] [pos="A.*"]; tabulate Matches match.text_lang match.id match[-5]..match[-1].word match..matchend.word matchend[1]..matchend[5].word match.substr(pos,0,1);' | tt-cqp --output=json
[[{'id':'match.text_lang', 'label':'{%Language} (match)'}, {'id':'match.id', 'label':'{%id} (match)'}, {'id':'match[-5]..match[-1].word', 'label':'{%word} (match[-5]..match[-1])'}, {'id':'match..matchend.word', 'label':'{%word} (match..matchend)'}, {'id':'matchend[1]..matchend[5].word', 'label':'{%word} (matchend[1]..matchend[5])'}, {'id':'match.substr(pos,0,1)', 'label':'{%Word Class} (match)'}, ],
['ES', 'w-27', 'de hayer estube en la', 'casa corresponsal', 'de Almeida y me dijo', 'NCFS000N'],
['ES', 'w-148', 'mi hyja  abandonè mi', 'casa nativa', 'sin extraer cosa alga de', 'NCFS000N'],
['ES', 'w-459', 'bien  dejar me mi', 'casa libre', 'de su persona , y', 'NCFS000N'],
['ES', 'w-168', ' se entro en mi', 'casa solo', 'sin llamar y se subio', 'NCFS000N'],
['PT', 'w-38', 'dias ẽ esta mão a', 'casa nova', 'de onde me mudarão pa', 'NCFS000N'],
['PT', 'w-351', 'o quall ove em esta', 'casa grandisimas', 'deszensois a reepeito de outra', 'NCFS000N'],
]
```

### XIDX output

There is another sense in which TT-CQP can produce XML output: when used together with TT-CWB-ENCODE, instead of giving results
from the CQP corpus itself, TT-CQP can lookup the underlying part of the XML files used as input for the CQP corpus. For
this, the command `xidx A` is used, which will give the whole string from the XML file starting from the start of the
token behind match, and ending with the token behind matchend, including anything in the middle, independenty of whether that was
indexed in the CQP corpus or not. If match and matchend do not belong to the same XML file, an empty string is given. This means that the
resulting XML cannot be guaranteed to be valid, since match and matchend might not belong to the same XML node. Therefore, the
raw results are given, one result per line (linebreaks within the result are removed). 
The output can hence not directly be parsed as XML, but it can be rendered
in a browser, which will automatically repair the XML.

```xml
user> echo 'Matches = [word="casa"] [pos="A.*"]; xidx Matches;' | tt-cqp
<tok id="w-27" mfs="NCFS000" lemma="casa">casa</tok> <tok id="w-28" mfs="AQ0CS0" lemma="corresponsal">corresponsal</tok>
<tok id="w-148" lemma="casa" mfs="NCFS000">casa</tok> <tok id="w-149" mfs="AQ0FS0" lemma="nativo">nativa</tok>
<tok id="w-459" lemma="casa" mfs="NCFS000">casa</tok> <tok id="w-460" lemma="libre" mfs="AQ0CS0">libre</tok>
<tok id="w-168" lemma="casa" mfs="NCFS000">casa</tok> <tok id="w-169" lemma="solo" mfs="AQ0MS0">solo</tok>
<tok id="w-38" lemma="casa" mfs="NCFS000">casa</tok> <tok id="w-39" lemma="novo" mfs="AQ0FS0">nova</tok>
<tok id="w-351" lemma="casa" mfs="NCFS000">casa</tok> <lb id="e-37"/> <tok id="w-352" nform="grandíssimas" mfs="AQSFP0" lemma="grande">grandisimas</tok>
``` 

The XIDX output gives a range starting from the beginning of the first XML token and ending with the last. This means
that `Matches = [word="casa"]; expand Matches to s; xidx Matches;` will give all sentences containing the word "casa", but
will not capture the actual &lt;s&gt; node since it will only render things starting from the first word. To get the whole
XML of the sentence, you have to tell the XIDX to expand (which will not affect the actual result list) so to get a list of &lt;s&gt; nodes you should use:

```
Matches = [word="casa"]; xidx Matches expand to s;
``` 

### SQL mode

For ranges (mostly for texts), TT-CQP also support a simple version of SQL (using --mode=sql). This mode will
only look at ranges, and ignore corpus positions entirely, treating the metadata as if it were a (relational) database. 
To select the year and title of all Portuguese texts written after 1600, you can use:

```sql
SELECT year, title FROM text WHERE lang="PT" && year > 1600;
```

### External annotations

In TT-CQP, you can read in an external XML file containing additional positional attributes; this is mostly meant to store external
annotations, say those made by visitors of a website, that in the design of the corpus cannot be included in the corpus itself. 
An external annotation file is a simple XML file linking corpus positions to any number of attributes, as in the following example:

```xml
<annotation>
	<item cpos="1334" type="Correct"/>   
	<item cpos="2622" type="Wrong"/> 
	<item cpos="204604" type="Correct"/>   
	<item cpos="268666" type="Correct"/>   
	<item cpos="112548" type="Correct"/>   
</annotation>
```

You load an external annotation by adding --extann=[filename], and are addressed like sattributes, using extann_type in this case.
There is no limit to the number of attributes associated with an item, but only one external attributes file can be loaded at
a time. Although they have the form of an sattribute, extann can only refer to single corpus positions.

### Small differences

* The tabulate in TT-CQP stops at the boundaries set by `within`, whereas CQP does not search beyond the within limits,
but does always display context to the left and the right even if that does not belong to the same s/text/etc.

* TT-CQP shows terminal colours only in interactive mode, and only with cat; and it does not add spaces around the match, 
and uses red bold text (as for instance in grep) rather than white-on-black. 

* `cat` in TT-CQP does not show the corpus positions but only the actual results, making it easier to post-process.

* TT-CQP will cast any string to an integer when using a > b or a < b conditions

* In TT-CQP you can use _ to get the corpus position, so target._ will give the corpus position of the target. You can use this 
for instance to check whether the head of a word comes before the word: a._ > head(a)._

* Instead of "descending" you can also use DESC in sorting, as you would in SQL.

* TT-CQP is not case-senstive when selecting a corpus, but only lets you select a corpus by just typing in the name if you have not 
yet selected a corpus - to switch, use "use corpusname" instead, as you would in SQL.

* `set Context 5` means 5 tokens in TT-CQP, showing a fixed number of character is not supported at this point

* In TT-CQP, "best" is a reserved name pointing to the token used first in the CQP query

## Use cases

### Word Sketches

One of the functions offered by TEITOK is to create something similar to the Word Sketch
from the Sketch Engine. The majority of the work for this is done by TT-CQP, and this
is how that is done. For this, we need a CWB corpus with dependency relations, which 
can be generated automatically from for instance a CONLL format.
 
The base of the word sketch is a CQL query - let's say we want to know the most common
words for which the lemma "casa" is an argument. We start by defining a named query:
`Matches = [lemma="casa"];`;

Now what we want to know is what the lemma of the head of these results is. For that,
we need to have the head indicated, and furthermore have the head defined as a relpos
field, ie. we need to have a file head.corpus.pos - either generated by TT-CWB-ENCODE
or from the command line from an existing CWB corpus. Having that, we can simply group
the results on the lemma of that headword: `group Matches head.lemma;`. This will create
mutual information and chi-square scores of the most typical head lemmas.

Finally, in a Word Sketch, the heads are not simply listed, but grouped by the type
of head. For this, we use the deps relation identifying the relation type between
the word and its its head. We do not want these to be taken into account in the 
statistics, but we do want to see them, which we can do by using `show:deps`. 

So creating the raw data for a Word Sketch in TT-CQP can be done by the following 
one-liner, after which we merely need to visualize the results:

```
echo 'Matches = [lemma="casa"]; group Matches head.lemma :: show:deps;' | tt-cqp
```

### Concordance Checking

In Spanish, the adjective (following the noun) should match the noun in number and gender. 
If they do not, there often is a tagging error, meaning it is useful to search the corpus
for all occurrences where noun and adjective do not match in gender or number.
In the EAGLES tagset, the number is indicated by the 4th position for nouns, and the 5th 
position for adjectives. In order to check whether these match in CQP, we can only check
one combination at a time, say `[pos="NC.S.*"] [pos="AQ..P.*"]` to check for singular nouns
followed by plural adjectives. In TT-CQP, we can use the substr function to get direct access
to the positions that we need. So to check for noun/adjective pairs that mismatch in number,
we can use the following one-liner:

```
echo 'Matches = a:[pos="NC.*"] b:[pos="AQ.*"] :: a.substr(pos,3,1) != b.substr(pos,4,1); tabulate Matches match.word match.pos matchend.word matchend.pos;' | tt-cqp
```

Note that the current limitations on TT-CQP mean we cannot extend this to a full match, since we cannot yet allow 
tokens between the noun and the adjective, nor can we use a disjunction between two conditions (only
conjunctions are supported at this time).