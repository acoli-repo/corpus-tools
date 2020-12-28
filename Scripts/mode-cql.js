// This only defines high-level behaviour of the Mode like folding etc.
ace.define('ace/mode/cql', ['require', 'exports', 'ace/lib/oop', 'ace/mode/text', 'ace/mode/custom_highlight_rules'], (acequire, exports) => {
  const oop = acequire('ace/lib/oop');
  const TextMode = acequire('ace/mode/text').Mode;
  const CQLHighlightRules = acequire('ace/mode/cql_highlight_rules').CQLHighlightRules;

	var Mode = function() {
		this.HighlightRules = CQLHighlightRules;
	};
  oop.inherits(Mode, TextMode); // ACE's way of doing inheritance

  exports.Mode = Mode; // eslint-disable-line no-param-reassign
});

// This is where we really create the highlighting rules
ace.define('ace/mode/cql_highlight_rules', ['require', 'exports', 'ace/lib/oop', 'ace/mode/text_highlight_rules'], (acequire, exports) => {
  const oop = acequire('ace/lib/oop');
  const TextHighlightRules = acequire('ace/mode/text_highlight_rules').TextHighlightRules;

  const CQLHighlightRules = function CQLHighlightRules() {
    var keywordMapper = this.createKeywordMapper({
        "variable.language": "this",
        "keyword": 
            " EQ NE LT LE GT GE NOT AND OR XOR IN LIKE BETWEEN",
        "constant.language": 
            "TRUE FALSE NULL SPACE",
        "support.type": 
            "c n i p f d t x string xstring decfloat16 decfloat34",
        "keyword.operator":
            "abs sign ceil floor trunc frac acos asin atan cos sin tan" +
            " abapOperator cosh sinh tanh exp log log10 sqrt" +
            " strlen xstrlen charlen numofchar dbmaxlen lines" 
    }, "text", true, " ");

    var compoundKeywords = "WITH\\W+(?:HEADER\\W+LINE|FRAME|KEY)|NO\\W+STANDARD\\W+PAGE\\W+HEADING|"+
        "EXIT\\W+FROM\\W+STEP\\W+LOOP|BEGIN\\W+OF\\W+(?:BLOCK|LINE)|BEGIN\\W+OF|";

    this.$rules = {
        "start" : [
            {token : ["variable", "space", "operator"], include: "whitespace", regex : "([A-Z]+[a-z0-9]*)(\\s*)(=)", next  : "token"},
            {token : "variable", regex : "[A-Z]+[a-z0-9]*\\s*", next  : "globalstart"},
            {token : "token", regex : "\\[", next  : "tokatt"},
            {token : "variable", regex : "[a-z_]+:", next  : "token"},
            {token : "operator", regex : "\\s*::\\s*", next  : "globalstart"},
            {token : "keyword", regex : "\\s*(cat|count|cut|group|sort|size|tabulate)\\s*", next  : "catatt"},
        ],
        "token" : [
            {token : "token", regex : "\\[", next  : "tokatt"},
        ],
        "tokatt" : [
            {token : "entity.other.attribute-name",   regex : "[a-z_]+", next : "tokeq"},
        ],
        "tokeq" : [
            {token : "operator",   regex : "\\!?=", next : "tokval"},
        ],
        "tokval" : [
            {token : "string",   regex : "\"[^\"]+\"", next : "tokclose"},
        ],
        "tokclose" : [
            {token : ["token", "operator"],   regex : "(?:\\s*)(\\])([+?*]?)", next : "start"},
            {token : "operator",   regex : "\\s*[&|]\\s*", next : "tokatt"},
        ],
        "globalstart" : [
            {token : ["keyword", "operator"],   regex : "(match|target|matchend)(\.)", next : "globalatt"},
            {token : "operator",   regex : ";", next : "start"},
        ],
        "globalatt" : [
            {token : "entity.other.attribute-name",   regex : "[a-z_]+", next : "globaleq"},
        ],
        "globaleq" : [
            {token : "operator",   regex : "\\!?=", next : "globalval"},
        ],
        "globalval" : [
            {token : "string",   regex : "\"[^\"]+\"", next : "globalclose"},
        ],
         "globalclose" : [
            {token : "keyword",   regex : "\\s*within\\s*", next : "withinatt"},
            {token : "operator",   regex : "\\s*[&|]\\s*", next : "tokatt"},
            {token : "operator",   regex : "\\s*;\\s*", next : "start"},
        ],
        "withinatt" : [
            {token : "entity.other.attribute-name",   regex : "[a-z_]+", next : "globaleq"},
        ],
        "catatt" : [
            {token : "variable",   regex : "[A-Z]+[a-z0-9]*", next : "catdef" },
        ],
        "catdef" : [
            {token : "operator",   regex : "\\s*;", next : "start"},
            {token : ["space", "keyword", "space", "operator", "space", "keyword", "space", "entity.other.attribute-name"],   regex : "(\\s*)(match|matchend|target)(\\s*)(\\.\\.\\.)(\\s*)(match|matchend|target)(\\s+)([a-z_]+)", next : "catdef" },
            {token : ["space", "keyword", "space", "entity.other.attribute-name"],   regex : "(\\s*)(match|matchend|target)(\\s+)([a-z_]+)", next : "catdef" },
            {token : ["space", "keyword"],   regex : "(\\s*)(matchend|target)", next : "catdef" },
            {token : ["space", "keyword"],   regex : "(\\s*)(match)", next : "catdef" },
            {token : "constant.numeric",   regex : "\\s*[0-9]+", next : "catdef" },
        ],
        "catend" : [
            {token : "operator",   regex : "\\s*;", next : "start"},
            {token : "operator",   regex : "\\s*,", next : "catdef"},
        ],
    };
  };

  oop.inherits(CQLHighlightRules, TextHighlightRules);

  exports.CQLHighlightRules = CQLHighlightRules;
});