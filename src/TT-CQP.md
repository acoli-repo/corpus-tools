# TT-CQP

TT-CQP is a custom version of the Corpus Query Processor from the Corpus WorkBench, written for 
the TEITOK corpus environment; for the sake of 
compatibility, TT-CQP uses exactly the same files as CQP, meaning you can use TT-CQP to search any
existing indexed CQP corpus. However, it was designed to be used together with TT-CWB-ENCODE, which
is a likewise custom version of CWB-ENCODE, which writes a couple of files that CWB-ENCODE does not;
because of that, some of the options of TT-CQP are not available unless the corpus was made using 
TT-CWB-ENCODE.

TT-CQP is not meant as a replacement for CQP - it implements various of the functions of CQP, while
adding some that CQP does not, but it is not a full re-implementation of CQP, nor is it intended to
become one, although more options will be added over time. Given the overlap between
CQP and TT-CQP, a full idea about how to use TT-CQP can be obtained by consulting the
manual of CQP, together with this document highlighting the differences.

### Options yet to be implemented

* it is not yet possible to have wildcards on tokens, such as `[pos="ADJ"]*`
* pattributes cannot match other attributes yet, such as `[word=a.word]`
* `:: within sattribute` does not yet work
* case and diacritic insensitive search not yet supported (%cd)
* it is not yet possible to cut lists
* ranged in tabulate output
* subcorpora cannot yet be modified by subset, intersection, join, or difference

### Options not planned to be implemented

* interactive mode - TT-CQP is meant for a piped architecture and will not have a real interactive mode
* all option related to the interactive mode are hence also unsupported (set, show, dump, cat, count, discard, save)
* macros, distance, distabs will not be implemented
* groupings and boolean operators are unlikely to be implemented
* randomize sort should be done in post-processing tools
* reduce is not likely to be implemented
* structural attributes will not be implemented (&lt;s&gt;, /region, expand) - but the can be used as global constraints (`:: match.year = "1990"`)
* aligned corpora will not be supported
* sort does not yet do reverse order (but does descending order)

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
* `head.word` will render the word of the corpus position marked as the head of either match, or target when set. This 
feature relies on a file `head.corpus.pos`, which matches corpus positions to the related position for the
pattribute head (head can be any
pattribute, but this feature is intended for dependency relations). Named positions take preference over related
positions.
* context shifts can be used with any of the position types, so `adj[1].lemma` will give the lemma first position to the
right of a named position "adj", and `head[-2].word` will give to the second word to the left of the head of match or target.

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
`sort A match.text_year` will sort the results in A on the year of the text (for match), and
`sort A head[1].substr(pos,0,1) descending` will sort in descending order by the first letter of the part-of-speech 
tag of the first token to the right of the head of match/target (who doesn't want to sort on that?). 
Instead of "descending" you can also use DESC. You cannot (yet) search on ranges as in `sort A by word on matchend[1]..matchend[10]`; 

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
or JSON format, where each tab of the output is marked with its key. An example of the XMl output is given below:

```
pwd> echo 'Matches = [word="casa"] [pos="A.*"]; tabulate Matches match.text_lang match.id match.word match[1].word substr(match.pos,0,1);' | tt-cqp --output=xml
<results cql="[word=&quot;casa&quot;] [pos=&quot;A.*&quot;]" tab="match.text_lang match.id match.word match[1].word substr(match.pos,0,1)" size="6">
	<result>
		<tab key="match.text_lang" val="PT" />
		<tab key="match.id" val="w-27" />
		<tab key="match.word" val="casa" />
		<tab key="match[1].word" val="corresponsal" />
		<tab key="substr(match.pos,0,1)" val="N" />
	</result>
	<result>
	...
</results>
```

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
echo 'Matches = a:[pos="NC.*"] b:[pos="AQ.*"] :: substr(a.pos,3,1) != substr(b.pos,4,1); tabulate Matches match.word match.pos matchend.word matchend.pos;' | tt-cqp
```

Note that the current limitations on TT-CQP mean we cannot extend this to a full match, since we cannot yet allow 
tokens between the noun and the adjective, nor can we use a disjunction between two conditions (only
conjunctions are supported at this time).