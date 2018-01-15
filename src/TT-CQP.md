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

* it is not yet possible to have wildcards on tokens, such as [pos="ADJ"]*
* pattributes cannot match other attributes yet, such as [word=a.word]
* :: within sattribute does not yet work
* only matching (potentially with regex) is supported for now, so you cannot do: > < !=
* case and diacritic insensitive search not yet supported (%cd)
* soring does not yet do reverse or descending order
* it is not yet possible to cut lists
* subcorpora cannot yet be modified by subset, intersection, join, or difference

### Options not planned to be implemented

* interactive mode - TT-CQP is meant for a piped architecture and will not have a real interactive mode
* all option related to the interactive mode are hence also unsupported (set, show, dump, cat, count, discard, save)
* macros, distance, distabs will not be implemented
* groupings and boolean operators are unlikely to be implemented
* randomize sort should be done in post-processing tools
* reduce is not likely to be implemented
* structural attributes will not be implemented (<s>, /region, expand) - but the can be used as global constraints ( :: match.year = "1990" )
* aligned corpora will not be supported

## Added options

### Grouping

Grouping in TT-CQP works slightly different from the way it works in CQP, and the group command more closely 
resembles the tabulate command; where CQP uses `group A match word by match lemma`, TT-CQP uses 
`group A match.word match.lemma`; the reason for this difference is to allow the additional tabulate options to 
be used in grouping, so you can group by `match[-1].substr(pos,0,1)`, `a[1].lemma`, or `head.word`;

 