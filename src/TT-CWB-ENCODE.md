# TT-CWB-ENCODE

## Configuration

## Additional files

The files written by tt-cwb-encode are the same as written by cwb-encode and cwb-makeall.
However, tt-cwb-encode writes a number of additional files that are used by either
tt-cwb-xidx or tt-cqp. 

### Positional attributes

For a pattribute "word", tt-cwb-encode writes the following additional files:

* word.corpus.pos - 

### Structural attributes

For an sattribute "s", tt-cwb-encode writes the following additional files:

* s_xidx.rng - for each s region in the XML files, a map from the index in the s.rng to the byte offset in the XML file
* s.idx - for each position in the corpus, the index in the s.rng it belongs to

For a pattribute "text_lang", tt-cwb-encode writes the following additional files:

* text_lang.idx - a mapping from each corpus position to the rng index of 