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
become one, although more options will be added over time

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
Instead of descending you can also use DESC. You cannot (yet) search on ranges as in `sort A by word on matchend[1]..matchend[10]`; 

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

### XML output

When using the option --output=xml, TT-CQP will produce the output of the group and tabulate command in XML format,
where each tab of the output is marked with its key. An example is given below:

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

 