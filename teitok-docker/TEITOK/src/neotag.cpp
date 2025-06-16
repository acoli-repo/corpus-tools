#include <boost/algorithm/string.hpp>
#include "pugixml.hpp"
#include <iostream>
#include <fstream>  
#include <string>
#include <sstream>
#include <set>
#include <map>
#include <vector>
#include <ctime>
#include <list>
#include <locale>
#include <getopt.h>
#include <math.h>

// This is a version of NeoTag tag tags directly in XML
// (c) Maarten Janssen, 2015

using namespace std;
using namespace boost;

// header
class wordtoken;
class parsepath;
string applylemrule ( string word, string rule );
string getsetting ( string key );
vector<wordtoken> morphoParse( string word, wordtoken insertword );

// global variables
int debug;			/* -d option */
int verbose;			/* -v option */
int forcelemma = 0;	/* force the forms as lemma if no lemma can be found */
float lexsmooth = 0;	/* force to look for alternative POS for known words */
float homsmooth = 0;	/* force to look for alternative POS for known words using homograph pairs */
float transitionsmooth = 0;	/* force to consider even never seen transitions */
bool featuretags;	/* the tags are build up of features feature:value;feature:value */
bool positiontags;	/* the tags are build up of features feature:value;feature:value */
int endlen = 6;		/* the amount of ending chars to be taken into account */
int endretry = 0;	/* the amount of ending chars to proceed after finding a match */
int neologisms = 0;	/* flag to indicate whether we are looking for neologisms */
int linenr = 0;
int logres = 0;
int partialclitic = 0; // to keep track of whether st starts with an optional clitic
float transitionfactor = 1; // how much (more/less) the transition prob counts than the lexical prob

wchar_t* mb2wchart(const char* ptr)
{
    std::mbstate_t state = std::mbstate_t(); // initial state
    const char* end = ptr + std::strlen(ptr);
    int len;
    wchar_t wc;
    wchar_t* wstr;
    int wpos = 0;
    while((len = std::mbrtowc(&wc, ptr, end-ptr, &state)) > 0) {
        wstr[wpos] = wc;
        ptr += len;
        wpos++;
    }
    wstr[wpos] = '\0';
    
    return wstr;
}

int mbchrlen ( wchar_t wc ) {
    std::mbstate_t state = std::mbstate_t();
	std::string mb(MB_CUR_MAX, '\0');
    int ret = std::wcrtomb(&mb[0], wc, &state);
	return ret;
};

string finalstop = "pos:PUNC";

string dtokout = "line";
ostream* outstream;
istream* instream;
bool tofile;
bool test = false;

// globals for counts
set<string> tagset; // the tagset for this language
map<string,float> tagProb; // the tagset for this language

	int wordnr = 0;
	int totparses = 0;
	int totlexparses = 0;
	int lexnr = 0;
	int totpaths = 0;
	
	int contextlength = 1; // the number of tags to the left to take into account
	float contextfactor = 2; // the context of length n is counted by (contextfactor ^ n)

// Create structures for the probabilities
map<string, map<string,wordtoken> > endingProbs; 	// the word-end driven probabilities
map<string, map<string,float> > transitionProbs; 	// the transition probabilities
map<int, map<string, map<string,float> > > transitionProbs2; 	// the transition probabilities (context > 1)
map<string, map<string,wordtoken> > posProbs; 		// the training set lexical probabilities
map<string, map<string,wordtoken> > lexiconProbs; 	// the lexical probabilities in the external lexicon
map<string, map<string,int> > lemmaProbs; 			// the lexical probabilities in the external lemmalist
map<string, map<string,float> > caseProb; 			// the case probabilities (% of tag in case)
map<string, map<string,int> > homPairs; 			// homograph pairs, used for lexical smoothing

map<string,int>  lemTagProb; 			// the list of POS used in the external lemmalist
map<string,int>  lexTagProb; 			// the list of POS used in the external lexicon

vector<string> &split(const string &s, char delim, vector<string> &elems) {
    stringstream ss(s);
    string item;
    while(getline(ss, item, delim)) {
        elems.push_back(item);
    }
    return elems;
}

map<string, string> pairparse (const string &s ) {
    vector<string> pairs;
    map<string, string> mapOne;
    if ( s.size() == 0 ) { return mapOne; };
    pairs = split(s, ';', pairs);

    for ( int i=0; i< pairs.size(); ++i ) {
	    vector<string> elems;
        elems = split(pairs.at(i), ':', elems);
        if ( elems.size() > 1 ) { mapOne[elems.at(0)] = elems.at(1); };
        
    }
    
    return mapOne;
}

void verboseout ( string text, int level ) {
	if ( debug >= level ) {
		cout << text;
	};
};

string mainpos ( string tag ) {
	if ( featuretags ) {
		string feature = "pos"; 
		map<string,string> morpho;
		morpho = pairparse( tag ); 
		return morpho[feature];
	} else if ( positiontags ) {
		return tag.substr(0,1);
	} else { return ""; };
};

string int2str(int number)
{
   stringstream ss;//create a stringstream
   ss << number;//add number to the stream
   return ss.str();//return a string with the contents of the stream
}

// String to Lowercase
// should be made UTF 
string strtolower ( string str ) {
  string lowercase = "";  int i=0;
  char c;
  while (str[i])
  {
    c=str[i];
    lowercase.append(1, tolower(c));
    i++;
  }
  return lowercase;
};
string strtoupper ( string str ) {
  string uppercase = "";  int i=0;
  char c;
  while (str[i])
  {
    c=str[i];
    uppercase.append(1, toupper(c));
    i++;
  }
  return uppercase;
};


// define a global structure to hold the statistical data
map <string, float> stats;
map <string, string> settings;


// class to hold a clitic
class clitic {
	public:
	string form;
	string lemma;
	string tag;
	float prob;
	int freq;
	string prepost;
	
};
vector<clitic> cliticList;

// class to hold a specific analysis for a word
class wordtoken {
	public:
	string form;
	string lemma;
	string lemrule;
	string wcase;
	
	string dforms;
	string dlemmas;
	
	map<string,int> lemmatizations; // a list of possible lemmas, to match the best one
	string tag;
	string pos;
	map<string,string> morpho; // the analyzed tag into features
	string source; // where this parse comes from (corpus, lexicon, clitics, pos, ...)
	string id;
	list<wordtoken> dtoks;

	map<string,int> lexprobs; // a list of all possible tags on this position for the last tag in this path

	// we do not need to keep this with every token interpretation....
	string input_lemma;
	string input_tag;
	
	int freq;
	float prob;

	void setform(string tmp) {
		form = tmp;
	
		// determine whether this word is upper or lowercase
		if ( form.size() > 0 ) {
			if ( isupper(form.at(0)) && isupper(form.at(form.size()-1)) && form.size() > 1 ) { wcase = "UU"; } 
			else if ( isupper(form.at(0)) ) { wcase = "Ul"; } 
			else if ( islower(form.at(0)) ) { wcase = "ll"; } 
			else { wcase = "??"; };
		};
	};
	
	void applycase() {
		// determine the most likely case for this word and apply it
		float maxprob = 0; string lcase;
		map<string,float> tmp = caseProb[tag];
		for (map<string,float>::const_iterator it=tmp.begin(); it!=tmp.end(); ++it) {
			if ( it->second > maxprob ) {
				lcase = it->first;
				maxprob = it->second;
			};
		};
		if ( lcase != wcase && lcase != "??" && lcase != "" ) {
			// verboseout ( " -- applying case " + lcase + " to " + lemma + " (" + tag + ") currently in " + wcase + endl, 5  );
			if ( debug > 4 ) { cout << " -- applying case " << lcase << " to " << lemma << " (" << tag << ") currently in " << wcase << endl; };
			if ( lcase == "ll" || lcase == "Ul" ) { 
				lemma = strtolower(lemma); 
			};
			if ( lcase == "UU" ) { 
				lemma = strtolower(lemma); 
			};
			char c; c = lemma[0]; 
			string tmp = ""; tmp.append(1,toupper(c));
			if ( lcase == "Ul" ) { lemma.replace(0,1,tmp); };
			if ( debug > 3 ) { cout << " -- applied case switch to " << lcase << " = " << lemma << " (" << tag << ") from " << wcase << endl; };
		};
	};
	
	void adddtok (wordtoken newdtok) {
		// Flatten the list of dtoks to allows have a flat list
		// or should we make this allow nested DTOK? Nesting is fine/nice but difficult to do on stdout
		if ( newdtok.dtoks.size() > 0 ) {
			// flatten the dtok list by recursive adding
			for (list<wordtoken>::iterator it2 = newdtok.dtoks.begin(); it2 != newdtok.dtoks.end(); it2++) {
				adddtok (*it2);
			};
		} else {
			dtoks.push_back(newdtok);
		};
	};
	
	void settag(string tmp) {
		tag = tmp;
		
		// if so desired, parse the tag into features and determine the main POS
		if ( tag.size() > 0 ) { 
			if ( featuretags == 1 || dtokout == "line" ) {
				// using CorpusWiki style structured features
				// format: pos:VAL;feature:val+{=}pos2:VAL;feature:val;
				dtoks.clear();
				vector<string> dtokset;
				dtokset = split(tag, '+', dtokset);
				
				vector<string> dtoklemmas;
				dtoklemmas = split(dlemmas, '+', dtoklemmas);
				vector<string> dtokforms;
				dtokforms = split(dforms, '+', dtokforms);
				
				string maintag = dtokset.at(0);
				dtokset.erase(dtokset.begin());
				
				// turn the first tok into the main tag
				morpho = pairparse ( maintag ); 
				pos = morpho["pos"];

				// if there are any, create dtoks for the other elements on the list
  				if ( debug > 4 ) { cout << " --- handling dtoks for " << tag << ", " << form << ", " << pos << endl; };
				 for ( int i = 0; i < dtokset.size(); i++ ) { 
					string dtag = dtokset[i];
					if ( dtag.substr(0,1) == "=" ) { dtag = dtag.substr(1); };
					if ( debug > 4 ) { cout << " --- treating dtok tag " << i << ". "<<  dtag << ", " << dforms << ", " << dlemmas << endl; };
					wordtoken dtoken;

					if ( dtoklemmas.size() > i ) {
						string tmp = dtoklemmas[i];
 						if ( tmp.size() ) { 
 							if ( tmp.substr(0,1) == "=" ) { tmp = tmp.substr(1); };
 							dtoken.lemma = tmp; 
 						} else { dtoken.lemma = "??"; };
					};
					
 					if ( dtokforms.size() > i ) {
 						tmp = dtokforms[i];
 						if ( tmp.size() ) { 
 							if ( tmp.substr(0,1) == "=" ) { tmp = tmp.substr(1); };
 							dtoken.form = tmp; 
 						} else { dtoken.form = "??"; };
 					};
	
					dtoken.tag = dtag;
					adddtok(dtoken);
				};
				if ( dtokout == "line" ) { tag = maintag; };
				
			} else if ( positiontags ) {
				// using a position-based tagset
				pos  = tag.substr(0,1);
				morpho["pos"] = pos;
				for ( int it = 1; it < tag.size(); it++ ) { morpho[int2str(it)] = tag.substr(it,1); };
			};
		};
	};

	void lemmatize () {
		// choose the best applicable lemrule from lemmatizations
		if ( lemma.size() == 0 && lemmatizations.size() == 0 ) {
			for ( int i = 0;  i < form.size(); i++ ) {
				string wending = form.substr(i, form.size());
				if ( endingProbs[wending][tag].lemmatizations.size() > 0 ) {
					lemmatizations = endingProbs[wending][tag].lemmatizations;
					i = form.size(); // break on the longest ending with lemmatizations
				};
			};
		};
		if ( lemma.size() == 0 && lemmatizations.size() > 0) { 
			int maxlem = 0; 
			if ( debug > 2 ) { cout << "   -- lemmatization options: " << lemmatizations.size() << endl; };
			for (map<string,int>::const_iterator it=lemmatizations.begin(); it!=lemmatizations.end(); ++it) {
			  if ( it->second > maxlem ) {
				string usedrule = it->first;
				string tmp = applylemrule (form, usedrule);
				if ( tmp.size() > 0  ) {
					lemma = tmp;
					if ( lemrule.size() ) { lemrule = lemrule + " + " + usedrule; }
					else { lemrule = usedrule; };
					maxlem = it->second;
				};
			  }		
			}
		};
		if ( lemma.size() == 0 ) {
			// if unable to lemmatize, return <unknown> or the word when using forcelemma
			if ( forcelemma ) {	
				lemma = form;
			} else {
				lemma = "<unknown>";
			};
		};
	};
	
    bool operator<(const wordtoken& b) const {
		return prob < b.prob;
    };
	
};


// Apply a lemmatization rule to a specific wordform
// We already know the lemmatization rule applies to this tag
// so we only give it the form and the rule as input
string applylemrule ( string word, string rule ) { 
	string lemma; string prefix; string suffix;
	string root; root = word;
	if ( debug > 4 ) { cout << "  - applying lemrule " << rule << " to " << word << " ==> " << endl; };
	
	vector<string> temp; split ( rule, '#', temp );
	string wrdtr = temp[0]; string lemtr = temp[1]; 
	
	// first apply the bits required on the beginning and the end
	while ( lemtr[0] != '*' && lemtr.size() > 0 ) {
		prefix = prefix + lemtr[0]; lemtr.erase(0,1); 
		if ( debug > 4 ) { cout << "  - added: " << prefix << ", " << lemtr << endl; };
	};
	while ( lemtr[lemtr.size()-1] != '*'  && lemtr.size() > 0 ) {
		suffix = lemtr[lemtr.size()-1] + suffix; lemtr.erase(lemtr.size()-1,1); 
		if ( debug > 4 ) { cout << "  - added: " << suffix << ", " << lemtr << endl; };
	};
	while ( wrdtr[0] != '*' && root.size() > 0 ) {
		if ( root[0] != wrdtr[0] ) { 
			if ( debug > 4 ) { cout << "  - not applicable: " << root[0] << ", " << wrdtr[0] << endl; };
			return ""; 
		};
		wrdtr.erase(0,1); root.erase(0,1); 
		if ( debug > 4 ) { cout << "  - removed: " << root << ", " << wrdtr  << endl; };
	};
	
	// now, recursively treat the bits at the end
	int wrdidx = root.size()-1;
	while ( wrdtr.size() > 0 && root.size() > 0 ) {
		while ( wrdtr[wrdtr.size()-1] != '*' ) {
			if ( root[wrdidx] != wrdtr[wrdtr.size()-1] ) { 
				if ( debug > 4 ) { cout << "  - not applicable: " << root << "-" << root[wrdidx] << ", " << wrdtr << "-" << wrdtr[wrdtr.size()-1] << endl; };
				return ""; 
			};
			wrdtr.erase(wrdtr.size()-1,1); root.erase(wrdidx,1); 
			if ( debug > 4 ) { cout << "  - removed: " << root << ", " << wrdtr << endl; };
			// if we have a character in the replacement as well, insert that here
			while ( lemtr[lemtr.size()-1] != '*' && lemtr.size() > 0 ) {
				root.insert(wrdidx,lemtr,lemtr.size()-1,1);
				lemtr.erase(lemtr.size()-1,1);
			};
			wrdidx--;
		};
		if ( wrdtr[wrdtr.size()-1] == '*' ) { 
			wrdtr.erase(wrdtr.size()-1,1);
		};
		if ( lemtr[lemtr.size()-1] == '*' ) { 
			lemtr.erase(lemtr.size()-1,1);
		};
		if ( wrdtr.size() > 0 ) { 
			while ( root[wrdidx] != wrdtr[wrdtr.size()-1] && wrdidx > 0 ) {
				wrdidx--;
			};
		};
		
	};
	
	if ( 1==2 ) { // rule.size() > 0 Not all the parts of the rule could be matched 
		if ( debug > 3 ) { cout << "  - not applicable" << endl; };
		lemma = ""; 
	} else { 
		lemma = prefix + root + suffix;
		if ( debug > 3 ) { 
			cout << "  - applied lemrule " << rule << " to " << word << " ==> " << lemma << endl;
		};
	};
	
	return lemma;
};

// Create a rule to build the lemma from the wordform
// This is being training on the training lexicon 
// and optionally the external full-form lexicon
vector<string> lemrulemake ( string wrd, string lmma ) {
	// this works, minus that it sometimes matches the wrong chars
	// heren/heer = **r*n#**r instead of ***en#**e*
	// aangetroffen/aantreffen = ***ge**off**#******ffe* instead of ***ge**o****#*****e****
	// these should stay, but lead to multiple options
	
	vector<string> lemmatizations;
	
	// Match as many characters between lemma and form as possible
	int wrdidx=0; int lemidx = 0;
	string wrdroot = wrd; string lemroot = lmma; 
	// find each char of lemidx in turn in the lemma
	while ( lemidx < lemroot.size() ) {
		// walk through the form until we find a match for the lemchar
		while ( wrdroot[wrdidx] != lemroot[lemidx] && wrdidx < wrdroot.size() ) {
			wrdidx++;
		};
		if ( wrdidx < wrdroot.size() ) { // match found
			wrdroot[wrdidx] = '*'; 	lemroot[lemidx] = '*'; 	
		} else { // no match found - rewind to just after the last * in the form and skip a lemchar
			while ( wrdroot[wrdidx] != '*' && wrdidx > 0 ) { wrdidx--; };
			wrdidx++;
		};
		lemidx++;
	};
	
	if ( wrdroot.size() == 0 || lemroot.size() == 0 ) {
		return lemmatizations;
	};
	string lemrule = wrdroot + '#' + lemroot;
	
	// remove multiple * in the rule
	wrdidx = 0; int star = 0;
	while ( wrdidx < lemrule.size() ) {
		if ( lemrule[wrdidx] == '*' ) {
			if ( star ) {
				lemrule.erase(wrdidx,1);
			} else { wrdidx++; };
			star = 1;
		} else {
			wrdidx++; star = 0;
		};
	};
	lemmatizations.push_back(lemrule);
	
	return lemmatizations;
};

// build the database of likelihoods based on word ending
void endlemmas ( string word, string tag, string lemma ) {
	int startat = word.size() - 1;

	// calculate the lemmatization rule(s)
	vector<string> lemmatizations = lemrulemake ( word, lemma );

	// calculate the frequency of each tag/ending pair
	if ( startat > endlen && endlen > 0 ) { startat = endlen; }; // only look at a maximum of (endlen) characters back
	int i = word.size() - startat;
	while ( i < word.size() ) {
		string wending = word.substr(i, word.size());
		if ( endingProbs[wending][tag].prob ) {
			endingProbs[wending][tag].prob += 1; // or : freq - or: something in between
		} else {
			wordtoken newword;
			newword.prob = 1; // or : freq
			endingProbs[wending][tag] = newword;
		};
		
		// add each lemmatization with its likelyhood
		for (int j=0;j<lemmatizations.size();j++) { 
			string lemrule = lemmatizations[j];
			endingProbs[wending][tag].lemmatizations[lemrule] += 1; // or : freq #! should we lemmatize by type or token frequency?
		};
		
		i++;
	};
	
};

// Container for a set of wordtokens and its probability, with add and print
class tokpath {
	public:
	float prob;
	float lexprob;
	list<wordtoken> toklist;

	private: 
	int ii, ij;
	
	public:

	void addtok ( wordtoken newword ) {
		if ( toklist.size() == 0 ) {
			prob = newword.prob;
		// } else if ( prob == 0 ) {
		// 	prob = newword.prob;
		} else { 
			string lasttag1 = toklist.back().tag;
			string lasttag2;
			list<wordtoken>::iterator it = toklist.end(); it--; if (it != toklist.begin()) { 
				it--; 
				lasttag2 = (*it).tag + "#" + toklist.back().tag;
			} else { lasttag2 = "***"; };
			float transitionprob1 = transitionProbs[lasttag1][newword.tag] + transitionsmooth; // transition probabilities, smoothed if so desired
			float transitionprob = transitionprob1 + transitionsmooth; // transition probabilities, smoothed if so desired
			// add for each longer context to the transition probability
			for ( int i=2; i<=contextlength; i++ ) {
				transitionprob = transitionprob + ( pow(contextfactor,i) * transitionProbs2[i][lasttag2][newword.tag] );
			};
			float caseprob1;
			if ( lasttag1 == finalstop ) {
				caseprob1 = 1;
			} else {
				caseprob1 = caseProb[newword.tag][newword.wcase]; 
			};
			// prob thusfar, the newword prob, the transition prob, the prob based on the case of the word
			float newprob = prob * newword.prob * pow(transitionprob,transitionfactor) * caseprob1; 
			if ( debug > 3 ) { 
				cout << " -- considering path: " << str() << " + " << newword.form << "/" << newword.tag  << "/" << newword.lemma << endl
					<< "     path likelihood: " << newprob << " = " << prob <<  " (old path) * " << newword.prob <<  " (lex prob) " 
					<< " * " << transitionprob << " (trans prob) = "
					<< transitionprob1 << " (context 1) for " << lasttag1 ;
				for ( int i=2; i<=contextlength; i++ ) {
					cout << " + " <<  ( pow(contextfactor,i) * transitionProbs2[i][lasttag2][newword.tag]) << " (context " << i << ") for " << lasttag2;
				};
				cout << " + " << transitionsmooth << " (trans smooting)" 
					<< " * " << caseprob1  << " (case prob " << newword.wcase << ")" 
					<< endl; 
			};
			prob = newprob;
		};
		toklist.push_back(newword);
	};
	

	void print ( ) {
		// printout the current list of paths
		ii = 0;
		if ( debug > 2 ) { cout << " - tokpath, prob = " << prob << endl; };
		for (list<wordtoken>::iterator it = toklist.begin(); it != toklist.end(); it++) {
			(*it).lemmatize();
	
			string besttag;
			if (  getsetting("lexprobs") != "" ) {
			  besttag = (*it).tag; // take the best lexical probability instead of the tag of the best path 
			  	/* This is not implemented yet! */
			} else {
			  besttag = (*it).tag;
			};
			// this is the main output - either to file or to stdout
			if ( ( tofile ) || ( !tofile && ( !debug && !verbose ) ) ) {
				if ( linenr ) { *outstream << (*it).id << "\t" ; };
				*outstream << (*it).form << "\t" << besttag << "\t" << (*it).lemma;
				*outstream << "\t" << (*it).source; 
				if ( verbose > 1 && (*it).lemrule.size() ) { *outstream << " ~ " << (*it).lemrule; }; 
				*outstream << endl;

				// when so desired, output lines for dtoks
				if ( dtokout == "line" ) {
					for (list<wordtoken>::iterator it2 = (*it).dtoks.begin(); it2 != (*it).dtoks.end(); it2++) {
						// we should not just blindly output MWE dtoks....
						*outstream << "- dtok\t" ;
						*outstream << (*it2).form << "\t" << (*it2).tag << "\t" << (*it2).lemma;
						*outstream << "\t" << (*it2).source; 
						if ( verbose > 1 && (*it2).lemrule.size() ) { *outstream << " ~ " << (*it2).lemrule; }; 
						*outstream << endl;
					};
				};
			};
			
			stats["printout"]++;
			bool lemstat; int tagstat; bool oov;
			string statline; string statline2;
			if ( verbose || test )  {
				stats["source-"+(*it).source]++;
				if ( tagstat && (*it).source != "lexicon" && (*it).source.substr(0,6) != "corpus" ) {
					stats["oovcount"]++;
					oov = true;
				} else { 
					oov = false; 
					stats["voccount"]++;
				};
			};
			
			// if we are running in test mode, check and count tag correctness
			if ( test ) {
				// if we know what the main pos is, check if it matches
				if ( featuretags || positiontags ) {
					int mtagstat;
					string input_pos = mainpos( (*it).input_tag ); // check what the main POS of the input word is
					string output_pos = mainpos( besttag ); // check what the main POS of the output word is
					if ( (*it).input_tag == besttag ) {
						statline = "tag+";
						stats["taggood"] ++;
						stats["tagmgood"] ++;
						tagstat = 2;
					} else if ( input_pos == output_pos && input_pos != "" ) {
						statline = "\ttag~:" + (*it).input_tag;
						stats["tagmgood"] ++;
						tagstat = 1;
					} else {
						statline = "\ttag-:" + (*it).input_tag;
						stats["tagwrong"] ++;
						tagstat = 0;
					};
					if ( oov && tagstat > 1 ) { stats["oovtaggood"] ++; };
					if ( oov && tagstat > 0 ) { stats["oovmtaggood"] ++; };
					if ( !oov && tagstat > 1 ) { stats["voctaggood"] ++; };
					if ( !oov && tagstat > 0 ) { stats["vocmtaggood"] ++; };

				} else {
					if ( (*it).input_tag == besttag ) {
						statline = "tag+";
						stats["taggood"] ++;
						tagstat = true;
					} else {
						statline = "\ttag-:" + (*it).input_tag;
						stats["tagwrong"] ++;
						tagstat = false;
					};
					if ( oov && tagstat ) { stats["oovtaggood"] ++; };
					if ( !oov && tagstat > 1 ) { stats["voctaggood"] ++; };
				};
	
				if ( (*it).input_lemma == (*it).lemma ) {
					statline2 = "lem+";
					stats["lemgood"] ++;
					lemstat = true;
				} else if ( strtolower((*it).input_lemma) == strtolower((*it).lemma) ) {
					statline2 = "\tlem~:" + (*it).input_lemma;
					stats["lemigood"] ++;
					lemstat = false;
				} else {
					statline2 = "\tlem-:" + (*it).input_lemma; // + ") ~ (" + (*it).lemma + ")";
					stats["lemwrong"] ++;
					lemstat = false;
				};
				if ( oov && lemstat ) { stats["oovlemgood"] ++; };
				if ( tagstat && lemstat ) { stats["taglemgood"] ++; };
				if ( oov && tagstat && lemstat ) { stats["tagoovlemgood"] ++; };


			};
			
			// this is the secondary output to stdout in verbose mode
			if ( debug || ( verbose && !tofile ) ) {
				if ( linenr ) { cout << (*it).id << "\t" ; }
				else if ( debug ) { cout << stats["printout"] << "\t" ; };
				cout << (*it).form << "\t" << besttag << "\t" << (*it).lemma;
				cout << "\t" << (*it).source; 
				if ( verbose > 1 && (*it).lemrule.size() ) { cout << " ~ " << (*it).lemrule; }; 

				if ( debug || verbose ) { cout << "\t" << statline << "\t" << statline2; };
				
				cout << endl; 
			
				// when so desired, output lines for dtoks
				if ( dtokout == "line" ) {
					for (list<wordtoken>::iterator it2 = (*it).dtoks.begin(); it2 != (*it).dtoks.end(); it2++) {
						*outstream << "- dtok\t" ;
						*outstream << (*it2).form << "\t" << (*it2).tag << "\t" << (*it2).lemma;
						*outstream << "\t" << (*it2).source; 
						if ( verbose > 1 && (*it2).lemrule.size() ) { *outstream << " ~ " << (*it2).lemrule; }; 
						*outstream << endl;
					};
				};
			};

		};
	};

	string str ( ) {
		// printout the current list of paths
		stringstream ss; string sep  = "";
		ii = 0;
		for (list<wordtoken>::iterator it = toklist.begin(); it != toklist.end(); it++) {
			ss << sep << (*it).form << "/" << (*it).tag << "/" << (*it).lemma;
			sep = " ";
		};
		return ss.str();
	};

    bool operator<(const tokpath& b) const {
		return prob < b.prob;
    };
	
};

// Container to contain the current set of paths/parses
class parsepath {
	public:
	int size;
	int length;
	list<tokpath> pathlist;
	
	private: 
	int ii, ij;
	
	public:
	
	void print ( ) {
		// printout the current list of paths
		ii = 0;
		*outstream << " -- " << pathlist.size() << " available paths: " << endl;
		for (list<tokpath>::iterator it = pathlist.begin(); it != pathlist.end(); it++) {
			cout << "   * path " << ++ii << " - prob = " << (*it).prob << endl;
			(*it).print();
		};
	};
	
	tokpath best ( string tag = "" ) {
		// return the best path, either in general, or for paths ending on TAG
		tokpath maxpath;
		float maxvalue = 0;
		for (list<tokpath>::iterator it = pathlist.begin(); it != pathlist.end(); it++) {
			if ( (*it).prob > maxvalue && 
				( (*it).toklist.back().tag == tag || tag == "" )
				) {
				maxpath = *it;
				maxvalue = (*it).prob;
				if ( debug > 3 ) { cout << "    current best : " << maxvalue << ", " << maxpath.toklist.back().tag << endl; };
			};
		};
		
		return maxpath;
	};
	
	void addword ( vector<wordtoken> newword ) {
		map<string,tokpath> newpathlist; // keep a map with only the best path for each (end) tag
		newpathlist.clear();
		if ( pathlist.size() == 0 ) { 
			for(ii=0; ii < newword.size(); ii++) {  
				tokpath newpath; newpath.prob = 0;
				newpath.addtok(newword[ii]);
				if ( newpathlist[newword[ii].tag].prob > newpath.prob ) {
					if ( debug > 3 ) { cout << "     suboptimal version for " << newword[ii].tag << endl; };
				} else {
					newpathlist[newword[ii].tag] = newpath;
				};
			};
		} else {
			// iterate though all the morphological parses for the new word, and calculate the newpath
			for (list<tokpath>::iterator it = pathlist.begin(); it != pathlist.end(); it++) {
				tokpath oldpath = *it;
				for(ii=0; ii < newword.size(); ii++) {  
					tokpath newpath;
					newpath = oldpath;
					newpath.addtok(newword[ii]);
					if ( newpath.prob > 0 ) {
						// store only if this is the best path for this endtag
						if ( newpathlist.find(newword[ii].tag) == newpathlist.end() ) { // we do not have a path yet for this tag
							if ( debug > 3 ) { cout << " 	 new path for " << newword[ii].tag << ", " << newpath.prob << endl; };
							newpathlist[newword[ii].tag] = newpath;
						} else if ( newpathlist[newword[ii].tag].prob < newpath.prob ) { // the current stored tag for this path is less likely
							if ( debug > 3 ) { cout << " 	 new optimal path for " << newword[ii].tag << endl; };
							newpathlist[newword[ii].tag] = newpath;
						 } else { // this is not a new path, and worse than what we have
							if ( debug > 4 ) { cout << " 	 suboptimal path" << endl; };
						};
					} else { // this is not a possible path at all
						if ( debug > 4 ) { cout << " 	 discarding as impossible path" << endl; };
					};
				};
			};
		};
		
		if ( newpathlist.size() == 0 ) {
			// no possible paths; choose the best before, and reset
			if ( debug > 1 ) { cout << " - dead end, printing out the best" << endl; }
			tokpath bestpath = best();
			if ( debug > 2 ) { cout << "   - best available path: " << bestpath.prob << endl; };
			bestpath.print(); // printout the best
			pathlist.clear();
			// removed the iterator form here, harmful?
			addword(newword); // reset by adding last word to zero path
		} else if ( newpathlist.size() == 1 ) {
			// unique possible path; print out, and reset
			
			// find the only remaining path (is there no better way?)
			string uniquetag; tokpath uniquepath;
			for (map<string,tokpath>::const_iterator it=newpathlist.begin(); it!=newpathlist.end(); ++it) {
			  uniquepath = it->second;
			  uniquetag = it->first;
			}

			if ( debug > 1 ) { cout << " - unique path, printing out, retaining " << uniquetag << endl; }

			// take the last word off from the list, and print the rest
			wordtoken lastword = uniquepath.toklist.back();
			uniquepath.toklist.pop_back();
			uniquepath.print();
			
			uniquepath.toklist.clear();
			uniquepath.toklist.push_back(lastword);
			uniquepath.prob = 1;
			pathlist.clear();
			pathlist.push_back( uniquepath );
			size=1;
			
		} else {

			// clear the current pathlist, and copy all elements of the new pathlist onto the pathlist
			// not just a copy, but turning a map into a list
			pathlist.clear();
			for (map<string,tokpath>::const_iterator it=newpathlist.begin(); it!=newpathlist.end(); ++it) {
			  // cout << " -- copying path to list of paths : " << it->first << " = " << it->second.prob << endl;
			  pathlist.push_back( it->second );
			}

			size = pathlist.size();
		};
	};
	
	bool operator<(const parsepath& b) const {
		return length < b.length;
    };

};

// Check whether a word ends/begins with "clitics" 
// or better - any string that should be treated as a separate word
void clitic_check ( wordtoken parseword, vector<wordtoken> * wordParse ) {
	string word = parseword.form;
	wordtoken insertword;
	
	// loop though all the possible clitics
	for ( int i=0; i<cliticList.size(); i++) {
		clitic cc = cliticList.at(i);
		if ( cc.prepost == "pre" && word.substr(0, cc.form.size()) == cc.form && word.size() > cc.form.size() ) {
			// a pre"clitic"
			string base = word.substr(cc.form.size());
			if ( cc.prob > 0 ) { // how could this be 0 ever?
				if ( debug > 2 ) { cout << " -- possible pre-clitic: " << cc.form << " + " << base << " : " << cc.tag << ", prob: " << cc.prob << ", freq: " << cc.freq << endl; };
				if ( base == word ) {
					throw std::string(" !!! not shortened, something is wrong, giving this up");
				} else {
					vector<wordtoken> baseParse = morphoParse(base, insertword);
					for ( int j=0; j<baseParse.size(); j++) {
						wordtoken cb = baseParse.at(j);
						if ( debug > 2 ) { cout << "    base word: " << cb.form << " : " << cb.tag << " = " << cb.prob << endl; };
						wordtoken insertword;
						insertword.setform(word);
						insertword.lemma = cc.lemma + "+" + cb.lemma;
						insertword.prob = cc.prob * cb.prob;
						insertword.id = parseword.id;
						insertword.source = "contractions + " + cb.source;
						wordtoken cctok; insertword.dtoks.clear();
						cctok.lemma = cc.lemma; cctok.form = cc.form; cctok.settag(cc.tag); cctok.source = "contractions";
						if ( dtokout == "line" ) {
							insertword.settag("pos:CONTR;");
						} else {
							insertword.settag("pos:CONTR#" + cc.tag + "+=" + cb.tag);
						};
						insertword.adddtok(cctok);
						insertword.adddtok(cb);
						wordParse->push_back(insertword);
					};
					if ( cc.prob < 1 ) { partialclitic = 1; }; // if this is not always a clitic, force to search on
				};
			};
		} else if ( cc.prepost == "post" && word.size() > cc.form.size() && word.substr(word.size()-cc.form.size()) == cc.form && word.size() > cc.form.size() ) {
			// a post"clitic"
			string base = word.substr(0,word.size()-cc.form.size());
			if ( cc.prob > 0 ) { // how could this be 0 ever?
				if ( debug > 2 ) { cout << " -- possible post-clitic: " << cc.form << " + " << base << " : " << cc.tag << " + " << base << ", prob: " << cc.prob << ", freq: " << cc.freq << endl; };
				vector<wordtoken> baseParse = morphoParse(base, insertword);
				for ( int j=0; j<baseParse.size(); j++) {
					wordtoken cb = baseParse.at(j);
					if ( debug > 2 ) { cout << "    base word: " << cb.form << " : " << cb.tag << " = " << cb.prob << endl; };
					wordtoken insertword;
					insertword.setform(word);
					insertword.id = parseword.id;
					insertword.source = cb.source + " + contractions";
					insertword.lemma = cb.lemma + "+" + cc.lemma;
					insertword.prob = cb.prob * cc.prob;
					wordtoken cctok; insertword.dtoks.clear();
					cctok.lemma = cc.lemma; cctok.form = cc.form; cctok.settag(cc.tag); cctok.source = "contractions";
					if ( dtokout == "line" ) {
						insertword.settag("pos:CONTR;");
					} else {
						insertword.settag("pos:CONTR#" + cb.tag + "+=" + cc.tag);
					};
					insertword.adddtok(cb);
					insertword.adddtok(cctok);
					wordParse->push_back(insertword);
				};
				if ( cc.prob < 1 ) { partialclitic = 1; }; // if this is not always a clitic, force to search on
			};
		};
	};
	
	// wordParse->push_back(insertword);
};

template <typename T>
string NumberToString ( T Number )
{
	stringstream ss;
	ss << Number;
	return ss.str();
}

// Morphological analysis
vector<wordtoken> morphoParse( string word, wordtoken parseword ) {	
	// calculate emission probabilities for word and return set of analyses

	vector<wordtoken> wordParse;
	wordtoken insertword;
	insertword = parseword;
	int totprob = 0;
	
	// step 1: see if we do not just know this word from the training corpus
	if ( posProbs[word].size() > 0 ) {
		// word found in the training corpus, copy to wordParses
		if ( debug > 1 ) { cout << " - " << posProbs[word].size() << " occurrence(s) found in training corpus " << endl; };
		map<string,wordtoken>::iterator pos;
		for (pos = posProbs[word].begin(); pos != posProbs[word].end(); ++pos) {
			if ( debug > 2 ) { cout << "   -  " << pos->first << ", " << pos->second.prob << endl; };
			insertword = pos->second;
			insertword.id = parseword.id;
			insertword.input_tag = parseword.input_tag;
			insertword.input_lemma = parseword.input_lemma;
			insertword.setform(word);
			insertword.settag(pos->first);
			insertword.source = "corpus:" + NumberToString(posProbs[word].size());
			if ( lemmaProbs[insertword.lemma][insertword.pos] < 1 && lemTagProb[insertword.pos] ) { 
				insertword.lemrule = "not in lemmalist"; 
			};
			if ( lexiconProbs[word][insertword.tag].prob < 1 && lexTagProb[insertword.tag] ) { 
				insertword.lemrule = "not in lexicon"; 
			};
			wordParse.push_back(insertword);
			totprob += insertword.prob;
		};
  	};

	string lowercase = strtolower(word);
	// step 1b: see if we do not know this word in lowercase from the training corpus
	if ( posProbs[lowercase].size() > 0  && wordParse.size() == 0) {
		// word in lowercase found in the training corpus, copy to wordParses
		if ( debug > 1 ) { cout << " - " << posProbs[lowercase].size() << " lowercase occurrence(s) of " << lowercase << " found in training corpus " << endl; };
		map<string,wordtoken>::iterator pos;
		for (pos = posProbs[lowercase].begin(); pos != posProbs[lowercase].end(); ++pos) {
			if ( debug > 2 ) { cout << "   -  " << pos->first << ", " << pos->second.prob << endl; };
			insertword = pos->second;
			insertword.id = parseword.id;
			insertword.setform(word);
			insertword.settag(pos->first);
			insertword.input_tag = parseword.input_tag;
			insertword.input_lemma = parseword.input_lemma;
			insertword.source = "corpus-" + posProbs[lowercase].size();
			insertword.lemrule = "lowercase";
			if ( !lemmaProbs[insertword.lemma][insertword.pos] && lemTagProb[insertword.pos] ) { 
				insertword.lemrule += "not in lemmalist"; 
			};
			if ( lexiconProbs[lowercase][insertword.tag].prob < 1 && lexTagProb[insertword.tag] ) { 
				insertword.lemrule = "not in lexicon"; 
			};
			wordParse.push_back(insertword);
			totprob += insertword.prob;
		};
	};
	
	// step 2: see if it happens to be a clitic/contracted word
	partialclitic = 0; // word that start with st which is sometimes a clitic should be treated as both
	if ( cliticList.size() > 0  && wordParse.size() == 0 && dtokout != "none" ) {
		wordtoken checkword; 
		checkword = parseword; checkword.form = word;
		clitic_check ( checkword, &wordParse);
	};
	
	// step 3: see if it happens to be in the external lexicon
	if ( lexiconProbs[word].size() > 0  && ( wordParse.size() == 0 || lexsmooth > 0 ) ) {
		// word found in the external lexicon, copy to wordParses
		if ( debug > 1 ) { cout << " - found in lexicon " << lexiconProbs[word].size() << endl; };
		map<string,wordtoken>::iterator pos;
		for (pos = lexiconProbs[word].begin(); pos != lexiconProbs[word].end(); ++pos) {
			if ( debug > 2 ) { cout << "   -  " << pos->first << endl; };
			insertword = pos->second;
			insertword.id = parseword.id;
			insertword.setform(word);
			insertword.settag(pos->first);
			insertword.prob = 1;
			insertword.input_tag = parseword.input_tag;
			insertword.input_lemma = parseword.input_lemma;
			insertword.source = "lexicon";
			insertword.lemrule = "";
			wordParse.push_back(insertword);
 			totprob += insertword.prob;
 		};
	};
		
	// step 4: use the end of the word (should become also beginning) to determine POS
	// when have not yet found the word, or when we want to lexically smooth
	// or when we have a word that only optionally starts with a clitic
	if ( wordParse.size() == 0 || lexsmooth  || partialclitic ) {
		int fnd = 0; float smoothfactor = 1;
		string smoothtxt = "";
		if ( lexsmooth ) { 
			smoothfactor = lexsmooth;
			if ( debug > 1 && wordParse.size() > 0 ) { 
				cout << " - forcing to look on with lexical smoothfactor " << smoothfactor << endl;
			};
			if ( wordParse.size() > 0 ) { 
				smoothtxt = " - lexically smoothed";
			};
		};
		for ( int i = 1;  i < word.size(); i++ ) {
			string wending = word.substr(i, word.size());
			if ( endingProbs[wending].size() > 0 && fnd <= endretry ) {
				fnd++;
				// word found in the external lexicon, copy to wordParses
				if ( debug > 1 ) { cout << " - found as ending " << word.size() - i << " = " << wending << " " << endingProbs[wending].size() << endl; };
				map<string,wordtoken>::iterator pos;
				for (pos = endingProbs[wending].begin(); pos != endingProbs[wending].end(); ++pos) {
					insertword.setform(word);
					insertword.settag(pos->first);
					insertword.prob = pos->second.prob * pow ( 5, 0-fnd) * smoothfactor; // count each shorter ending match by a power less
					insertword.lemmatizations = pos->second.lemmatizations;

					if ( lemmaProbs.size() > 0 ) {
						// insertword.tag2pos(); // calculate the pos of this word
						string pos = insertword.pos;
						int lemfound = 0; int maxlem = 0;
						
						if ( debug > 3 ) { cout << "   +  " << insertword.tag << ", " << insertword.prob  << endl; };
						if ( debug > 4 ) { cout << insertword.lemmatizations.size() + 0 << " lemmatization options for " << wending << " " << insertword.tag << endl; };
						// run through the lemmatization options here to see of any of them is in the lexicon
						for (map<string,int>::const_iterator it=insertword.lemmatizations.begin(); it!=insertword.lemmatizations.end(); ++it) {
							string lemrule = it->first;
							string lemma = applylemrule(insertword.form, lemrule);
							int lemprob = it->second * smoothfactor;
							if ( lemma.size() > 0 ) {
								// check the lemmatization result with the pos tag in the lemmalist
								if ( lemmaProbs[lemma][pos] == 1 ) { 
									// We should match against other features than pos as well - mostly for gender on nouns
									if ( debug > 2 ) { cout << "   - found in the lemmalist: " << lemma  << ", " << pos << " << " << insertword.tag << endl; }; //  << lemmaProbs[lemma][0] 
									wordtoken known_word = insertword;
									known_word.lemma = lemma;
									known_word.id = parseword.id;
									known_word.source = "lemmalist" + smoothtxt;
									known_word.lemrule = wending + " + " + lemrule;
									known_word.prob *= 20; // we need to make this much more likely given that we found direct evidence in the lemmalist
									known_word.applycase();
									wordParse.push_back(known_word);
									totprob += known_word.prob; lemfound = 1;
								} else {
									if ( debug > 3 ) { cout << "   - possible lemma (not in the lemmalist): " << lemma  << ", " << pos << " - freq " << lemprob << endl; }; //  << lemmaProbs[lemma][0] 
									if ( lemprob > maxlem ) {
										insertword.source = "ending" + smoothtxt;
										insertword.lemrule = wending + " + " + lemrule;
										insertword.lemma = lemma;
										maxlem = lemprob;
									};
								};
							};
						};
						if ( lemfound == 0 ) {
							if ( insertword.pos == "CONTR" ) { // a CONTRACTION should never be added only based on its ending.....
								if ( debug > 3 ) { cout << "   - we ended up with a contraction - rejecting: " << insertword.lemma  << ", " << insertword.pos << " - prob " << insertword.prob << endl; }; //  << lemmaProbs[lemma][0] 
							} else {
								if ( debug > 3 ) { cout << "   - adding it with most likely lemma: " << insertword.lemma  << ", " << insertword.tag  << ", " << insertword.pos << " - prob " << insertword.prob << endl; }; //  << lemmaProbs[lemma][0] 
								insertword.applycase();
								wordParse.push_back(insertword);
							};
							totprob += insertword.prob;
						};
						
					} else {

						if ( debug > 2 ) { cout << "   -  " << insertword.tag << ", " << insertword.prob << endl; };

						insertword.source = "ending ";
						insertword.lemrule = "" + wending;
						wordParse.push_back(insertword);
						totprob += insertword.prob;

					};
				};
			};
		};
	};
	
	// if all else fails, just produce unknown - or the form itself
	if ( wordParse.size() == 0 ) {
		if ( debug > 1 ) { cout << " - not found - defaulting to tag frequency " << endl; };
		map<string,float>::iterator pos;
		for (pos = tagProb.begin(); pos != tagProb.end(); ++pos) {
			insertword.setform(word);
			insertword.settag(pos->first);
			insertword.prob = pos->second;
			insertword.source = "pos";
			if ( pos->first.size() > 0 ) { wordParse.push_back(insertword); }; // avoid adding empty tags
			totprob += insertword.prob;
  		}
	};
	
	// now, normalize to prob [0,1]
	if ( totprob > 0 ) {
		for ( int i = 0;  i < wordParse.size(); i++ ) {
			wordParse[i].prob = wordParse[i].prob/totprob;
		};
	};
	
	return wordParse;
}

// Treat a word - parse it morphologically, and calculate the optimal paths
parsepath pathList;
void treatWord ( wordtoken insertword ) {
	wordnr++;

	string word = insertword.form;
	vector<wordtoken> wordParse;
	
	// do a morphological analysis of WORD to yield all possible tags
	// this will internally deal with lexicon lookup and morphological analysis
	// the result is a list of 
	if ( debug > 3 ) { cout << "-----------------------------------------" << endl; };
	if ( debug > 1 ) { 
		if ( test ) {
			cout << wordnr << ". "  << word << " - from test file: " << insertword.input_tag << "/" << insertword.input_lemma << endl; 
		} else {
			cout << wordnr << ". "  << word  << endl; 
		}
	};
	if ( debug > 3 ) { cout << "-----------------------------------------" << endl; };
	wordParse = morphoParse( word, insertword );
	totparses += wordParse.size();
	if ( wordParse[0].source.substr(0,6) == "corpus" ) {
		// if the first parse comes from the corpus, they all (or at least some) do
		lexnr++;
		totlexparses += wordParse.size();
	};

	// if we want lexical smoothing by homographs, furthermore add all homograph pairs for this word....
	if ( ( wordParse[0].source.substr(0,6) == "corpus" && homsmooth > 0 ) 
	  || ( ( wordParse[0].source.substr(0,6) == "corpus" || wordParse[0].source == "lemmalist" ) && ( homsmooth && neologisms ) ) ) {
		wordtoken smoothWord;
		smoothWord.setform(wordParse[0].form);
		smoothWord.input_tag =  wordParse[0].input_tag;
		smoothWord.input_lemma =  wordParse[0].input_lemma;
		int last = wordParse.size(); // we have to store the CURRENT size, or it will continue to grow
		for(int i=0; i < last; i++) {  
			string tag = wordParse[i].tag;
			for (map<string,int>::iterator it2 = homPairs[tag].begin(); it2 != homPairs[tag].end(); it2++) {
				smoothWord.tag = it2->first;
				smoothWord.prob = wordParse[i].prob * homsmooth * (it2->second/tagProb[tag]);
				smoothWord.source = "smoothing";
				if ( debug > 4 ) { cout << "   - lexically smoothing from " << tag << " > " << it2->first << " = " << smoothWord.prob << endl; };
				smoothWord.lemrule = wordParse[i].source + ": " + tag;
				wordParse.push_back(smoothWord);
			};
		};
	};
	
	// printout the # of morphological analyses for the current word  
	if ( debug > 1 ) { cout << " -- tagcount: " << wordParse.size() << endl; };
	
	// add the morphological option for this word to the path,
	// calculating the path likelihoods
	pathList.addword(wordParse);
	totpaths += pathList.pathlist.size();
	
	// sort the optimal paths by probability
	pathList.pathlist.sort();
	pathList.pathlist.reverse();
	
	// printout the current number of paths still under consideration
	if ( debug > 1 ) { cout << " -- number of (optimal) paths : " << pathList.size << endl; };
	if ( debug > 2 ) {
		for (list<tokpath>::iterator it = pathList.pathlist.begin(); it != pathList.pathlist.end(); it++) {
			cout << "    " << (*it).prob << " - " << (*it).str() << endl;
		};			
	};
};

string getsetting ( string key ) {
	if ( settings.find(key) == settings.end() ) {
		return "";
	} else {
		return settings[key];
	};
};

// Read settings from a parameter file
void readsettings ( string param ) {
	string filename;
	stringstream ss;
	if ( getenv("NEOTAG_CONFS") ) {
		ss << getenv("NEOTAG_CONFS");
	} else {
		ss << "/usr/local/lib/neotag";
	};
	ss << "/" << param << ".conf";
	ss >> filename;	

	ifstream filestreamer;	
	filestreamer.open(filename.c_str());
	if (filestreamer.is_open())
	{
		string line;
		while ( filestreamer.good() )
		{
			getline (filestreamer,line);
			istringstream ss( line );		
			string key; getline( ss, key, '\t' );
			string val; getline( ss, val, '\t' );
			if ( key != "" ) { 
				settings[key] = val; 
			};
		}
		filestreamer.close();
	} else { 
		cout << "No such parameter file: " << filename << endl;
	}
		
}

// Read the parameter files for the given language
void readparameters (char foldername[50]) {

	char filename[50];	
	string line;
		
	ifstream filestreamer;	
	// Read the transition probabilities 
	sprintf(filename, "%s/transitions.txt", foldername);
	filestreamer.open(filename);
	if (filestreamer.is_open())
	{
		while ( filestreamer.good() )
		{
			getline (filestreamer,line);
			istringstream ss( line );		
			string tag1; getline( ss, tag1, '\t' );
			string tag2; getline( ss, tag2, '\t' );
			string freqc; getline( ss, freqc, '\t' );
			float prob = atof(freqc.c_str());
			string rawfreq; getline( ss, rawfreq, '\t' );
			int freq = atoi(rawfreq.c_str());
			
			tagset.insert(tag1); tagset.insert(tag2); // keep track of tagset
			transitionProbs[tag1][tag2] = prob;
		}
		filestreamer.close();
	} else { 
		cout << "No transition frequencies in : " << filename << endl;
		exit(1);
	}
	
	sprintf(filename, "%s/transitions2.txt", foldername);
	filestreamer.open(filename);
	if (filestreamer.is_open())
	{
		while ( filestreamer.good() )
		{
			getline (filestreamer,line);
			istringstream ss( line );		
			string tag1; getline( ss, tag1, '\t' );
			string tag2; getline( ss, tag2, '\t' );
			string freqc; getline( ss, freqc, '\t' );
			float prob = atof(freqc.c_str());
			string rawfreq; getline( ss, rawfreq, '\t' );
			int freq = atoi(rawfreq.c_str());
			
			transitionProbs2[2][tag1][tag2] = prob;
		}
		filestreamer.close();
	} else { 
		if ( debug > 4 ) { printf("No transition frequencies for context 2\n"); };
	}
	
	// Read the lexical probabilities
	sprintf(filename, "%s/lexiconprobs.txt", foldername);
	filestreamer.open(filename);
	if (filestreamer.is_open())
	{
		stats["traintokens"] = 0;
		stats["lexcount"] = 0;
		while ( filestreamer.good() )
		{
			getline (filestreamer,line);
			istringstream ss( line );		
			string word; getline( ss, word, '\t' );
			string tag; getline( ss, tag, '\t' );
			string freqc; getline( ss, freqc, '\t' );
			float prob = atof(freqc.c_str());
			string rawfreq; getline( ss, rawfreq, '\t' );
			int freq = atoi(rawfreq.c_str());
			string lemma; getline( ss, lemma, '\t' );
			string pos; getline( ss, pos, '\t' );
			string dform; getline( ss, dform, '\t' );
			string dlemma; getline( ss, dlemma, '\t' );
			
			stats["lexcount"]++;
			wordtoken newword;
			newword.setform(word);
			newword.prob = prob;
			newword.lemma = lemma;
			newword.dforms = dform;
			newword.dlemmas = dlemma;
			
			posProbs[word][tag] = newword;
			tagProb[tag] = tagProb[tag] + freq;
			stats["traintokens"] = stats["traintokens"] + freq;
			
			endlemmas ( word, tag, lemma );
			
		}
		filestreamer.close();
	} else { 
		printf("No lexical probabilities!\n");
		exit(1);
	}

	// normalize the ending probabilities to [0,1] - relevant if we need to compare ending probs with other probs
	map<string, map<string,wordtoken> >::iterator pos;
	for (pos = endingProbs.begin(); pos != endingProbs.end(); ++pos) {
		string i = pos->first;
		int endingtot = 0;
		// calculate the total for each ending
		map<string,wordtoken>::iterator pos2;
		for (pos2 = pos->second.begin(); pos2 !=  pos->second.end(); ++pos2) {
			string j = pos2->first;
			endingtot += endingProbs[i][j].prob;
		};	
		// devide each number by the total
		for (pos2 =  pos->second.begin(); pos2 !=  pos->second.end(); ++pos2) {
			string j = pos2->first;
			endingProbs[i][j].prob = endingProbs[i][j].prob/endingtot;
		};	
	};
	
	// When using homograph smoothing
	// build a database of homographic pairs of tags from posProb\
	// only when using lexical smoothing
	if ( homsmooth ) {
		map<string, map<string,wordtoken> >::iterator it1;
		map<string,wordtoken>::iterator it2;
		map<string,wordtoken>::iterator it3;
		for (it1 = posProbs.begin(); it1 != posProbs.end(); ++it1) {
			string word = it1->first;
			for ( it2 = it1->second.begin(); it2 != it1->second.end(); ++it2) {
				string tag1 = it2->first;
				for ( it3 = it1->second.begin(); it3 != it1->second.end(); ++it3) {
					string tag2 = it3->first;
					if ( tag1 != tag2 && tag1 != "" && tag2 != "" && word != "" ) { 
						// why are these tags and words empty so often?
						// cout << word << " makes pair: " << tag1 << ", " << tag2 << endl;
						homPairs[tag1][tag2] += 1;
					};
				};
			};
		};
	};
	
	// Read the full-form lexicon (if it exists)
	char* lexiconfilename;
	if ( getsetting("lexicon") != "" ) { strcpy(filename, settings["lexicon"].c_str()); } 
	else { sprintf(filename, "%s/lexicon.txt", foldername); };
	
	filestreamer.open(filename);
	if (filestreamer.is_open())
	{
		while ( filestreamer.good() )
		{
			getline (filestreamer,line);
			istringstream ss( line );		
			string word; getline( ss, word, '\t' );
			string tag; getline( ss, tag, '\t' );
			string lemma; getline( ss, lemma, '\t' );

			wordtoken newword;
			newword.setform(word);
			newword.lemma = lemma;
			newword.prob = 1;
			lexiconProbs[word][tag] = newword;
			lexTagProb[tag]++;
			stats["lexiconcount"]++;
			
			endlemmas ( word, tag, lemma );

		}
		filestreamer.close();
	};
	
	// Read the tag/case file (if it exists)
	sprintf(filename, "%s/case.txt", foldername);
	filestreamer.open(filename);
	if (filestreamer.is_open())
	{
		while ( filestreamer.good() )
		{
			getline (filestreamer,line);
			istringstream ss( line );		
			string tag; getline( ss, tag, '\t' );
			string wcase; getline( ss, wcase, '\t' );
			string tmp; getline( ss, tmp, '\t' );
			int freq = atoi(tmp.c_str());
				getline( ss, tmp, '\t' );
			float prob = atof(tmp.c_str());

			caseProb[tag][wcase] = prob;
		}
		filestreamer.close();
	};
	
	// Read the dtok-parts file
	// containing contracted words
	sprintf(filename, "%s/dtokparts.txt", foldername);
	// ( $dform, $dlemma, $dtag, $prepost, $dfreq, $clno_type, $clno_token, $clyes_type, $clyes_token ) = @tmp;
	filestreamer.open(filename);
	if (filestreamer.is_open())
	{
		clitic newclitic;
		while ( filestreamer.good() )
		{
			getline (filestreamer,line);
			istringstream ss( line );		
			string form; getline( ss, form, '\t' );
				newclitic.form = form;
			string tag; getline( ss, tag, '\t' );
				newclitic.tag = tag;
			string lemma; getline( ss, lemma, '\t' );
				newclitic.lemma = lemma;
			string prepost; getline( ss, prepost, '\t' );
				newclitic.prepost = prepost;
			string dfreq; getline( ss, dfreq, '\t' );
			string cl_no_type; getline( ss, cl_no_type, '\t' );
			string cl_no_token; getline( ss, cl_no_token, '\t' );
			string cl_yes_type; getline( ss, cl_yes_type, '\t' );
			string cl_yes_token; getline( ss, cl_yes_token, '\t' );
			if ( cl_no_type.size() ) {
				newclitic.prob = atof(cl_yes_type.c_str())/atof(cl_no_type.c_str());
			} else {
				newclitic.prob = 1;
			};
			newclitic.freq = atoi(cl_yes_type.c_str());
			// cout << " - clitic : " << newclitic.form << ", " << newclitic.prob << endl;
			if ( newclitic.form.size() > 0 ) { cliticList.push_back(newclitic); }
			else { cliticList.push_back(newclitic); };

		}
		filestreamer.close();
	};

	
	// Read the dictionary/lemmalist 
	// (if it exists, and only if we have structured tags so that we know what the tags mean)
	if ( featuretags || positiontags ) {
		char* lexiconfilename;
		if ( getsetting("dictionary") != "" ) { strcpy(filename, settings["dictionary"].c_str()); } 
		else { sprintf(filename, "%s/lemmalist.txt", foldername); };
		// sprintf(filename, "%s/lemmalist.txt", foldername);
		filestreamer.open(filename);
		if (filestreamer.is_open())
		{
			while ( filestreamer.good() )
			{
				getline (filestreamer,line);
				istringstream ss( line );		
				string lemma; getline( ss, lemma, '\t' );
				string pos; getline( ss, pos, '\t' );
				string features; getline( ss, features, '\t' );
	
				lemmaProbs[lemma][pos] = 1;
				lemTagProb[pos]++;				
				
				stats["dictcount"]++;
	
			}
			filestreamer.close();
		};
	};
	
};

void help() {
	cout << "Usage: neotag [OPTIONS] [FILE]" << endl;
	cout << "Tag FILE or STDIN with Part-of-Speech tags, assumes one word per line as input" << endl << endl;
	cout << "Options:" << endl;
	cout << "  -?, --help\tThis help file" << endl;
	exit(1);
};

// Main 
int main (int argc, char * const argv[]) {

	// we somehow have to deal with UTF-8
	// char *loc_str = setlocale(LC_ALL, "en_US.UTF-8");
    setlocale(LC_CTYPE, "UTF-8");
	// locale loc; //("en_UK.UTF-8");

	string word;
	string input_line;
	clock_t beginT, endT;
	
	// Read language and text from commandline options
	int textid;             /* -t option */
	char foldername[50];
	char inFile[50] = "";
	char outFile[50] = "";
	
	
	beginT = clock();
	
	// Read in all the command-line arguments
	for ( int i=1; i< argc; ++i ) {
		
		string argm = argv[i];
		
		if ( argm.substr(0,2) == "--" ) {
		
			int spacepos = argm.find("=");
			
			if ( spacepos == -1 ) {
				string akey = argm.substr(2);
				settings[akey] = "1";
			} else {
				string akey = argm.substr(2,spacepos-2);
				string aval = argm.substr(spacepos+1);
				settings[akey] = aval;
			};

		};
		
	};

	if ( getsetting("params") != "" ) { 
		readsettings(getsetting("params"));
	};

	// define some of these variables as system variables
	if ( getsetting("debug") != "" ) { debug = atoi(settings["debug"].c_str()); };
	if ( getsetting("test") != "" ) { test = true; if (getsetting("verbose") == "") { verbose = 2; }; };
	if ( getsetting("linenr") != "" ) { linenr = true; };
	if ( getsetting("logres") != "" || getsetting("log") != "" ) { logres = true; };
	if ( getsetting("forcelemma") != "" ) { forcelemma = true; };
	if ( getsetting("featuretags") != ""  ) { featuretags = true; };
	if ( getsetting("positiontags") != "" ) { positiontags = true; };
	if ( getsetting("neologisms") != "" ) { neologisms = true; };
	if ( getsetting("help") != "" ) { help(); };
	if ( getsetting("verbose") != "" ) { verbose = atoi(settings["verbose"].c_str()); };
	if ( getsetting("endretry") != "" ) { endretry = atoi(settings["endretry"].c_str()); };
	if ( getsetting("endlen") != "" ) { endretry = atoi(settings["endlen"].c_str()); };
	if ( getsetting("lexsmooth") != "" ) { lexsmooth = atof(settings["lexsmooth"].c_str()); };
	if ( getsetting("homsmooth") != "" ) { homsmooth = atof(settings["homsmooth"].c_str()); };
	if ( getsetting("transsmooth") != "" ) { transitionsmooth = atof(settings["transsmooth"].c_str()); };
	if ( getsetting("transitionfactor") != "" ) { transitionfactor = atof(settings["transitionfactor"].c_str()); };

	if ( getsetting("contextfactor") != "" ) { contextfactor = atof(settings["contextfactor"].c_str()); };
	if ( getsetting("contextlength") != "" ) { contextlength = atoi(settings["contextlength"].c_str()); };
	if ( getsetting("context") != "" ) { contextlength = atoi(settings["context"].c_str()); };

	if ( getsetting("folder") != "" ) { strcpy(foldername, settings["folder"].c_str()); };
	if ( getsetting("infile") != "" ) { strcpy(inFile, settings["infile"].c_str()); };
	if ( getsetting("outfile") != "" ) { strcpy(outFile, settings["outfile"].c_str()); };
	if ( getsetting("dtokout") != "" ) { dtokout = settings["dtokout"]; };

	

	// the first non-option argument will be interpreted as input file
	//string argm = argv[argc];
	//if (argm.substr(0,2) != "--") { strcpy(inFile, argv[argc]); };

	// when running in testmode, try to use the test.txt file in the parameters folder
	if ( *inFile == 0 && test ) {		
		sprintf(inFile, "%s/test.txt", foldername);
		// check if test.txt actually exists and can be read
		ifstream file(inFile);
		if (file) {
			cout << "-- Running in test mode, reading from " << inFile << endl;
		} else {
			// Can't open file
			cout << "-- Running in test mode, but cannot read from " << inFile << endl;
			*inFile = 0;
		};
	};
	
	// read the parameter files
	readparameters (foldername);

	
	// write to file if so indicated, or else to STDOUT
	if ( *outFile != 0 ) { // outFile != "" ??
		outstream = new std::ofstream(outFile);                        //2
		tofile = true;
	} else {
		outstream = &std::cout;                                        //3
		tofile = false;
	};
	
	// read from file if so indicated, or else from STDIN
	if ( *inFile != 0 ) {
		instream = new std::ifstream(inFile,ifstream::in);             //2
	} else {
		instream = &std::cin;                                          //3
	};
	
	// If running in debug mode, output data about the training corpus
	if ( debug || test ) {
		printf("-------------------------------\n");
		printf("---- PARAMETER FILES READ -----\n");
		printf("----    configurations:   -----\n");
		printf("-------------------------------\n");
		printf("Training folder: %s\n", foldername);
		printf("Training corpus size: %d\n", int(stats["traintokens"]));
		printf("Training lexicon size: %d\n", int(stats["lexcount"]));
		printf("Tagset size: %d\n", (int) tagset.size());
		printf("External full-form lexicon size: %d\n", int(stats["lexiconcount"]));
		if ( featuretags ) printf("External lemmalist size: %d\n", int(stats["dictcount"]));
		printf("Preprocess time: %f\n", (float(clock())-float(beginT))/float(CLOCKS_PER_SEC));
		printf("-------------------------------\n");
		printf("Lexical smoothing: %f\n", (float) lexsmooth);
		printf("Transition smoothing: %f\n", (float) transitionsmooth);
		printf("Transition factor: %f\n", (float) transitionfactor);
		printf("Word-end length: %d\n", (int) endlen);
		printf("Word-end retry: %d\n", (int) endretry);

		printf("-------------------------------\n");
		printf("---        ANALYSIS        ----\n");
		printf("-------------------------------\n");
	};
	endT = clock();
	
	// keep reading words from STDIN until we have read all words
	// parse each word in turn calculating all optimal paths
	// printout whenever an unambiguous state is reached
	while( *instream ) {
		
		wordtoken insertword;

		// read a line from STDIN and parse the columns ( ?linenr, word, tag, lemma )
		getline(*instream, input_line);
		istringstream ss( input_line );		
		string word_id; if ( linenr ) { getline( ss, word_id, '\t' ); insertword.id = word_id; };
		string word; getline( ss, word, '\t' );	insertword.form = word;
		string input_tag; getline( ss, input_tag, '\t' ); insertword.input_tag = input_tag;
		string input_lemma; getline( ss, input_lemma, '\t' ); 
		input_lemma.erase(input_lemma.find_last_not_of(" \n\r\t")+1); // chop new lines
		insertword.input_lemma = input_lemma;
		
		if ( word.size() == 0 ) { continue; }; // ignore empty lines		
		// Here we should also ignore/through-put SGML tags
		
		
		if ( getsetting("tokenize") != "" ) { 
			stringstream ss(word);
			string linelmt;
			
			while(getline(ss, linelmt, ' ')) {
				wordtoken spaceword;

				// deal with leading punctuation marks (non UTF yet!)
				wchar_t* wstr = mb2wchart(linelmt.c_str());
				wordtoken punct;
				while ( iswpunct(wstr[0]) && sizeof(wstr) > 1 && wcslen(wstr) > 1 ) {
					int chrlen = mbchrlen(wstr[0]);
					punct.form = linelmt.substr(0,chrlen);
					treatWord(punct);
					linelmt = linelmt.substr(chrlen);
					// wstr = mb2wchart(linelmt.c_str());
					wcscpy(wstr, mb2wchart(linelmt.c_str()));
				};
				// strip the trailing punctuation marks (non UTF yet!)
				list<wordtoken> tail;
				while ( iswpunct(wstr[wcslen(wstr)-1]) && wcslen(wstr) > 1 ) {
					int chrlen = mbchrlen(wstr[wcslen(wstr)-1]);
		
					punct.form = linelmt.substr(linelmt.size()-chrlen,chrlen);
					tail.push_front(punct);
					linelmt = linelmt.substr(0,linelmt.size()-chrlen);
					//wstr = mb2wchart(linelmt.c_str());
					wstr[wcslen(wstr)-1] = L'\0';
				};
				
				// handle the word
				spaceword.form = linelmt;
				treatWord ( spaceword );
				
				// handle the trailing punctuation marks
  				for (std::list<wordtoken>::iterator it=tail.begin(); it!=tail.end(); ++it) {
  					treatWord(*it);
				};

			}
		} else {
			treatWord ( insertword );
		};

	};
	pathList.best().print();

	// when running in verbose mode, output data about the analysis
	if ( debug || test || verbose ) { 
		float elapsed = (float(clock())-float(endT))/float(CLOCKS_PER_SEC);
		float elapsed2 = (float(clock())-float(beginT))/float(CLOCKS_PER_SEC);
		if ( debug >1 || verbose >1) { cout << "------------------" << endl << "-- done parsing --" << endl << "------------------" << endl; }
		else {  cout << "--------------------------" << endl; };
		
		cout << wordnr << " tokens tagged in " << elapsed << " (" 
			<< wordnr/elapsed << " tk/s) - total time " << elapsed2 << endl; 
			
		cout << "average word ambiguity: " << float(totparses)/float(wordnr) << endl;
		cout << "average voc word ambiguity: " << float(totlexparses)/float(lexnr) << endl;
		cout << "average path ambiguity: " << float(totpaths)/float(wordnr) << endl;
		cout << "in-lexicon items: " << 100*(float(lexnr)/float(wordnr)) << "%" << endl;
	};

	// when running in verbose test mode, output data about the accuracy
	if ( test ) {
		cout << "---------- accuracy ------------" << endl;
		cout << "tag accuracy: " << 100*(float(stats["taggood"])/float(wordnr)) << "%" << endl;
		if ( featuretags || positiontags ) { cout << "main tag accuracy: " << 100*(float(stats["tagmgood"])/float(wordnr)) << "%" << endl; }
		cout << "oov/voc tag acc.: " << 100*(float(stats["oovtaggood"])/float(stats["oovcount"])) << " / " << 100*(float(stats["voctaggood"])/float(stats["voccount"])) << "%" << endl;
		if ( featuretags || positiontags ) { cout << "oov/voc main tag accuracy: " << 100*(float(stats["oovmtaggood"])/float(stats["oovcount"])) << " / " << 100*(float(stats["vocmtaggood"])/float(stats["voccount"])) << "%" << endl; }
		cout << "lemma accuracy: " << 100*(float(stats["lemgood"])/float(wordnr)) << "%" << endl;
		cout << "lemmatization accuracy: " << 100*(float(stats["taglemgood"])/stats["tagmgood"]) << "%" << endl;
		cout << "oov lemma acc.: " << 100*(float(stats["oovlemgood"])/float(stats["oovcount"])) << "%" << endl;
		cout << "oov lemmatization acc.: " << 100*(float(stats["tagoovlemgood"])/stats["oovtagmgood"]) << "%" << endl;

	};
	if ( verbose > 1 ) {
		cout << "---------- settings ------------" << endl;
		for (map<string,string>::const_iterator it=settings.begin(); it!=settings.end(); ++it) {
			cout << it->first << " : " << it->second << endl;   	
		}
		cout << "-------- stats counts ----------" << endl;
		for (map<string,float>::const_iterator it=stats.begin(); it!=stats.end(); ++it) {
			cout << it->first << " : " << it->second << endl;   	
		}
	};
	
	// when running in test mode, output accuracy line to log file
	if ( test && logres ) {
		char* logfilename = foldername;
		strcat ( logfilename, "/results.log" );
		ostream *logfile = new std::ofstream(logfilename,  std::ios::out | std::ios::app);
		// date
		time_t now = time(0);
		*logfile << now;
		// accuracy overview
		*logfile << "\t";
		*logfile << "acc:" << float(stats["taggood"])/float(wordnr) << ";";
		if ( featuretags || positiontags ) { *logfile << "mainacc:" << float(stats["tagmgood"])/float(wordnr) << ";"; }
		*logfile << "lemacc:" << float(stats["taglemgood"])/stats["tagmgood"] << ";";
		*logfile << "\t";
		for ( int i=1; i<argc ; i++ ) {
			*logfile << argv[i] << " ";
		};
		// stats
		*logfile << "\t";
		for (map<string,float>::const_iterator it=stats.begin(); it!=stats.end(); ++it) {
			*logfile << it->first << ":" << it->second << ";";   	
		}
		*logfile << endl;
	};
		
    return 0;
	
}

