#include <boost/algorithm/string.hpp>
#include <boost/filesystem.hpp>
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

// This is a version of NeoTag that tags directly in XML
// Designed within the TEITOK frameword: http://teitok.corpuwiki.org
// (c) Maarten Janssen, 2015

using namespace std;
using namespace boost;

// header
class wordtoken;
class parsepath;
string applylemrule ( string word, string rule );
vector<wordtoken> morphoParse( string word, wordtoken insertword );
pugi::xml_document taglog;
pugi::xml_node tagsettings;
pugi::xml_node tagstats;
map < string, string > inherit;
pugi::xpath_node_set pattlist; 
pugi::xml_document parameters;

vector<string> formTags;
	string tagfld;
	string checkfld;
	string lemmafld;
	string tagpos;

// global variables
int debug;			/* -d option */
int verbose;			/* -v option */
float lexsmooth = 0;	/* force to look for alternative POS for known words */
float homsmooth = 0;	/* force to look for alternative POS for known words using homograph pairs */
float transitionsmooth = 0;	/* force to consider even never seen transitions */
bool featuretags;	/* the tags are build up of features feature:value;feature:value */
bool positiontags;	/* the tags are build up of features feature:value;feature:value */
bool neologisms;	/* flag to indicate whether we are looking for neologisms */
bool tagsrcshow;	/* flag to indicate whether to include tagsrc */
int endlen = 6;		/* the amount of ending chars to be taken into account */
int linenr = 0;
int logres = 0;
int partialclitic = 0; // to keep track of whether st starts with an optional clitic
float transitionfactor = 1; // how much (more/less) the transition prob counts than the lexical prob

string finalstop = "pos:PUNC";

bool dtoksout = false;
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
map<string, float > transitionProbs; 	// the transition probabilities
map<string, pugi::xpath_node_set  > posProbs; 		// the training set lexical probabilities
map<string, pugi::xpath_node_set  > endProbs; 		// the training set ending probabilities
map<string, map<string,wordtoken> > lexiconProbs; 	// the lexical probabilities in the external lexicon
map<string, map<string,int> > lemmaProbs; 			// the lexical probabilities in the external lemmalist
map<string, map<string,float> > caseProb; 			// the case probabilities (% of tag in case)
map<string, map<string,int> > homPairs; 			// homograph pairs, used for lexical smoothing

map<string,int>  lemTagProb; 			// the list of POS used in the external lemmalist
map<string,int>  lexTagProb; 			// the list of POS used in the external lexicon

map<string, string> pairparse (const string &s ) {
    vector<string> pairs;
    map<string, string> mapOne;
    if ( s.size() == 0 ) { return mapOne; };
    boost::split(pairs, s, is_any_of(";"));

    for ( int i=0; i< pairs.size(); ++i ) {
	    vector<string> elems;
        boost::split(elems, pairs.at(i), is_any_of(":"));
        if ( elems.size() > 1 ) { mapOne[elems.at(0)] = elems.at(1); };
        
    }
    
    return mapOne;
}

void verboseout ( string text, int level ) {
	if ( debug >= level ) {
		cout << text;
	};
};

string calcform ( pugi::xml_node node, string fld ) {
	string getfld = fld;
	while ( !node.attribute(getfld.c_str()) && inherit[getfld] != "" ) {
		getfld = inherit[getfld];
	};
	if ( getfld == "pform" ) {
		return node.child_value();
	} else {
		return node.attribute(getfld.c_str()).value();
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

// determine te case of a string
string formcase ( string form ) {
	string wcase;
	if ( form.size() > 0 ) {
		if ( isupper(form.at(0)) && isupper(form.at(form.size()-1)) && form.size() > 1 ) { wcase = "UU"; } 
		else if ( isupper(form.at(0)) ) { wcase = "Ul"; } 
		else if ( islower(form.at(0)) ) { wcase = "ll"; } 
		else { wcase = "??"; };
	} else wcase = "";
	return wcase;
};

// class to hold a specific analysis for a word
class wordtoken {
	public:
	string form;
	string lemma;
	string lemrule;
	string wcase;
	
	string dforms;
	string dlemmas;
	
	pugi::xml_node token;
	pugi::xml_node lexitem;
	
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
		// Flatten the list of dtoks to have a flat list
		// or should we make this allow nested DTOK? Nesting is fine/nice but might not be intended
		if ( newdtok.dtoks.size() > 0 ) {
			// flatten the dtok list by recursive adding
			for (list<wordtoken>::iterator it2 = newdtok.dtoks.begin(); it2 != newdtok.dtoks.end(); it2++) {
				adddtok (*it2);
			};
		} else {
			dtoks.push_back(newdtok);
		};
	};

	void lemmatize () {
		// lexitem has not <lemma> if there are dtok
		if ( lemma.size() > 0 || lexitem.attribute("lemma") != NULL || lexitem.child("dtok").attribute("lemma") != NULL ) { return; }; // skip if the item has a lemma already
		if ( debug > 5 ) { cout << "   -- " << form << " in need of lemmatization (trying from ending/lemmatizations) " << endl; };
		
		// If we found a lemmatization, apply it
		if ( lexitem.child("lemmatization") != NULL ) { 
			int maxlem = 0; 
			if ( debug > 2 ) { cout << "   -- lemmatization options: " << lemmatizations.size() << endl; };
			for ( pugi::xml_node lemopt = lexitem.child("lemmatization"); lemopt != NULL; lemopt = lemopt.next_sibling("lemmatization") ) {
				if ( debug > 4 ) { cout << "   -- lemmatization option: " << lemopt.attribute("key").value() << endl; };
			  if ( atoi(lemopt.attribute("cnt").value()) > maxlem ) {
				string usedrule = lemopt.attribute("key").value();
				string tmp = applylemrule ( calcform(token, lemmafld), usedrule );
				if ( tmp.size() > 0  ) {
					lemma = tmp;
					if ( lemrule.size() ) { lemrule = lemrule + " + " + usedrule; }
					else { lemrule = usedrule; };
					maxlem = atoi(lemopt.attribute("cnt").value());
				};
			  }		
			};
			return;
		};
		
		// if unable to lemmatize, return the form in --lemmatize
		if ( debug > 5 ) { cout << "   -- no lemmatization rules found, using " << lemmafld << " = " << calcform(token, lemmafld) << endl; };
		if ( lemmafld != "--" ) {
			lemma = "";
		} else if ( lemmafld != "form" ) {
			lemma = calcform(token, lemmafld);
		} else {
			lemma = form;
		};
	};
		
	void updatetok() {
		// Copy the information from the lexitem (or local info) onto the token
		if ( !strcmp(lexitem.name(), "tok") ) {
			// This is a known word - copy from the lexicon
			if ( debug > 4 ) { lexitem.print(std::cout); };
			lemma = lexitem.attribute("lemma").value();
			// TODO: this should listen to formtags
// 			for ( int i=0; i<formTags.size(); i++ ) {
// 				string totag = formTags[i];
// 				if ( token.attribute(totag.c_str()) == NULL && lexitem.attribute(totag.c_str()) != NULL ) { 
// 					token.append_attribute(totag.c_str()) =  lexitem.attribute(totag.c_str());
// 				};
// 			};
			for (pugi::xml_attribute_iterator it = lexitem.attributes_begin(); it != lexitem.attributes_end(); ++it) {
				if ( !strcmp((*it).name(), "key") || !strcmp((*it).name(), "cnt")) { continue; };
				if ( token.attribute((*it).name()) == NULL ) { 
					token.append_attribute((*it).name()) =  (*it).value();
				} else if ( tagsettings.attribute("overwrite") != NULL || !strcmp(token.attribute((*it).name()).value(), "") ) {
					token.attribute((*it).name()) =  (*it).value();
				};
			};
			if ( token.child("dtok") == NULL ) {
				for ( pugi::xml_node dtoken = lexitem.child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") ) {
					// TODO: use existing dtoks in the tagging XML when they exist rather than just adding new ones
					token.append_copy(dtoken);
				};
			};
		} else {
			if ( debug > 4 ) { lexitem.print(std::cout); };
			// This is a new word - add calculated lemma and tag
			if ( token.attribute(tagpos.c_str()) == NULL && tag != "" ) { 
				token.append_attribute(tagpos.c_str()) =  tag.c_str();
			};
			if ( token.attribute("lemma") == NULL && lemma != "" ) { 
				token.append_attribute("lemma") =  lemma.c_str();
			};
 			if ( dtoks.size() > 0 ) {
				for (list<wordtoken>::const_iterator it = dtoks.begin(); it != dtoks.end(); ++it) {
					if ( debug > 2 ) { cout << "    Generated dtok: " << it->form << endl; };
					pugi::xml_node dtoken = token.append_child("dtok");
					dtoken.append_attribute(tagpos.c_str()) =  it->tag.c_str();
					if ( it->lexitem.attribute("lemma") != NULL ) { dtoken.append_attribute("lemma") =  it->lexitem.attribute("lemma").value(); };
					cout << "dtoken added: " << endl;
				};
			};
		};
		if ( tagsrcshow ) {
			token.append_attribute("tagsrc") =  source.c_str();
		};
	};	
		
	void settag(string tmp) {
		tag = tmp;
		
		// We want to make sure to also determine the main POS
	};

	
    bool operator<(const wordtoken& b) const {
		return prob < b.prob;
    };
	
};

float getTransProb ( string transitionstring ) {
	if ( transitionProbs[transitionstring] ) {
		if ( debug > 4 ) { cout << "  Stored transition probability: " << transitionProbs[transitionstring] << " for " << transitionstring << endl; };
		return transitionProbs[transitionstring];
	} else {
		string xpath = "item[@key=\""+transitionstring+"\"]"; 
		float transitionprob = atoi(parameters.first_child().child("transitions").select_single_node(xpath.c_str()).node().attribute("cnt").value()); // transition probabilities, smoothed if so desired
		if ( debug > 4 ) { cout << "  Parameter transition frequency: " << transitionprob << " from " << xpath << endl; };
		transitionProbs[transitionstring] = transitionprob;
		return transitionprob;
	};
};

pugi::xpath_node_set getLexProb ( string word ) {
	if ( !posProbs[word].empty() ) {
		return posProbs[word];
	} else {
		string xpath = "item[@key=\""+word+"\"]/tok";
		pugi::xpath_node_set tmp = parameters.first_child().child("lexicon").select_nodes(xpath.c_str());
		posProbs[word] = tmp;
		return tmp;
	};
};

float getCaseProb ( wordtoken word  ) {
	
	string wcase;
	wcase = word.wcase; if ( wcase.length() == 0 ) { wcase = formcase(word.form); };
	if ( debug > 4 ) { cout << "  Calculating case probability for : " << word.form << "/" << word.tag  << "/" << wcase << endl; };
	if ( !caseProb[word.tag][word.wcase] ) {
		string xpath = "item[@key=\""+word.tag+"\"]/case[@key=\""+wcase+"\"]";
		pugi::xpath_node_set tmp = parameters.first_child().child("tags").select_nodes(xpath.c_str());
		if ( tmp.empty() ) {
			caseProb[word.tag][wcase] = 0;
		} else {
			pugi::xml_node it = tmp.begin()->node();
			int casecnt = atoi(it.attribute("cnt").value());
			int tagcnt = atoi(it.parent().attribute("cnt").value());
			float casepr = ((float)casecnt / (float)tagcnt);
			caseProb[word.tag][wcase] = casepr;
			if ( debug > 4 ) { cout << "  Case probability: " << word.form << "/" << word.tag << "/" << wcase << " = " << casecnt << " / " << tagcnt << " = " << caseProb[word.tag][wcase] << endl; };
		};
	};
	return caseProb[word.tag][wcase];
};

pugi::xpath_node_set getEndProb ( string word ) {
	int mblen = 0;
	for ( int i = endlen; i + mblen > 0; i-- ) {
		if ( word.length() > i ) {
			string wordend = word.substr(word.length()-i, word.length());
			if ( debug > 4 ) { cout << "    Trying ending: " << wordend << endl; };
			// Do not count parts of MB chars
			if ( ( *(wordend.substr(0,1).c_str()) & 0xc0 ) == 0x80 ) {  mblen++; continue; };
			if ( !endProbs[wordend].empty() ) {
				return endProbs[wordend];
			} else {
				string xpath = "item[@key=\""+wordend+"\"]/item";
				pugi::xpath_node_set tmp = parameters.first_child().child("endings").select_nodes(xpath.c_str());
				endProbs[wordend] = tmp;
				if ( !tmp.empty() ) { return tmp; };
			};
		};
	};
	pugi::xpath_node_set tmp;
	return tmp; // found nothing...
};

// Apply a lemmatization rule to a specific wordform
// We already know the lemmatization rule applies to this tag
// so we only give it the form and the rule as input
string applylemrule ( string word, string rule ) { 
	string lemma; string prefix; string suffix;
	string root; root = word;
	if ( debug > 4 ) { cout << "  - applying lemrule " << rule << " to " << word << " ==> " << endl; };
	
	vector<string> temp;
	boost::split ( temp, rule, is_any_of("#") );
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
		} else { 
			string lasttag1 = toklist.back().tag;
			string lasttag2;
			list<wordtoken>::iterator it = toklist.end(); it--; if (it != toklist.begin()) { 
				it--; 
				lasttag2 = (*it).tag + "#" + toklist.back().tag;
			} else { lasttag2 = "***"; }; // Consider the last tag impossible (should that be FS?)
			float transitionprob1 = getTransProb(lasttag1+"."+newword.tag); // transition probabilities, smoothed if so desired
			float transitionprob = transitionprob1 + transitionsmooth; // transition probabilities, smoothed if so desired
			// add for each longer context to the transition probability
			float caseprob1;
			if ( lasttag1 == finalstop ) {
				caseprob1 = 1;
			} else {
				// caseprob1 = caseProb[newword.tag][newword.wcase]; 
				caseprob1 = getCaseProb(newword);
			};
			// prob thusfar, the newword prob, the transition prob, the prob based on the case of the word
			float newprob = prob * newword.prob * pow(transitionprob,transitionfactor) * caseprob1; 
			if ( debug > 3 ) { 
				cout << " -- considering path: " << str() << " + " << newword.form << "/" << newword.tag  << "/" << newword.lemma << endl
					<< "     path likelihood: " << newprob << " = " << prob <<  " (old path) * " << newword.prob <<  " (lex prob) " 
					<< " * " << transitionprob << " (trans prob) = "
					<< transitionprob1 << " (" << lasttag1 << "." << newword.tag << ") ";
// 				for ( int i=2; i<=contextlength; i++ ) {
// 					cout << " + " <<  ( pow(contextfactor,i) * transitionProbs2[i][lasttag2][newword.tag]) << " (context " << i << ") for " << lasttag2;
// 				};
//				cout << " + " << transitionsmooth << " (trans smoothing)";
				cout	<< " * " << caseprob1  << " (case prob " << newword.wcase << "/" << newword.tag << ")" 
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
			if (  tagsettings.attribute("lexprobs") != NULL ) {
			  besttag = (*it).tag; // take the best lexical probability instead of the tag of the best path 
			  	/* This is not implemented yet! */
			} else {
			  besttag = (*it).tag;
			};
			
			// The real "print" is not here, but is in updating the token in the XML
			(*it).updatetok();
			
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
				if ( debug > 2 ) { cout << "****"; }; // Mark with stars to find output in the heavy verbose log			
				if ( linenr ) { cout << (*it).id << "\t" ; }
				else if ( debug ) { cout << stats["printout"] << "\t" ; };
				cout << (*it).form << "\t" << besttag << "\t" << (*it).lemma;
				cout << "\t" << (*it).source; 
				if ( verbose > 1 && (*it).lemrule.size() ) { cout << " ~ " << (*it).lemrule; }; 

				if ( debug || verbose ) { cout << "\t" << statline << "\t" << statline2; };
				
				cout << endl; 
			
				// when so desired, output lines for dtoks
				if ( dtoksout ) {
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
			if ( debug > 3 ) { cout << " - No possible paths left, getting last best option " << endl; };
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
	
	// loop though all the possible clitics
	pattlist = parameters.first_child().child("dtoks").select_nodes("dtok");
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it) {
		wordtoken insertword = parseword;	
		string ccform = it->node().attribute("form").value();
		string cctag = it->node().attribute(tagpos.c_str()).value();
		if ( ccform == "" ) { return; }; // Why would we ever reach a non-form clitic?
		float ccprob = atof(it->node().attribute("cnt").value()); // TODO: this should become prob
		string base = "";
		if ( !strcmp(it->node().attribute("position").value(), "left") && word.substr(0, ccform.size()) == ccform && word.size() > ccform.size() ) {
			// a pre"clitic"
			base = word.substr(ccform.size());
			if ( debug > 2 ) { cout << " -- possible pre-clitic of " << word << " : " << ccform << "/" << cctag << " = " << ccprob << " + " << base << endl; };
		} else if ( !strcmp(it->node().attribute("position").value(), "right") && word.size() > ccform.size() && word.substr(word.size()-ccform.size()) == ccform && word.size() > ccform.size() ) {
			// a post"clitic"
			base = word.substr(0,word.size()-ccform.size());
			if ( debug > 2 ) { cout << " -- possible post-clitic of " << word << " : " << base << " + " << ccform << "/" << cctag << " = " << ccprob << endl; };
		};
		if ( base != "" && base != word ) {
			vector<wordtoken> baseParse = morphoParse(base, insertword);
			for ( int j=0; j<baseParse.size(); j++) {
				wordtoken cb = baseParse.at(j);
				if ( debug > 5 ) { cout << "    base word: " << cb.form << " : " << cb.tag << " = " << cb.prob << endl; };
				// check if this base word tag occurs with the clitic
				string tmp = ".//sibling[@key=\""+cb.tag+"\"]";
				if ( it->node().select_nodes(tmp.c_str()).empty() ) { 
					if ( debug > 5 ) { cout << "    This base tag never appear with this clitic ~ " << tmp << endl; };
					continue;
				};
				insertword.prob = ccprob * cb.prob;
				insertword.source = "contractions: " + cb.source;
				insertword.dtoks.clear();
				wordtoken cctok; 
				// cctok.lemma = it->node().attribute("lemma").value(); 
				cctok.setform(ccform); cctok.settag(cctag); 
				cctok.lexitem = it->node(); cctok.source = "contractions";
				cb.lemmatize();
				if ( !strcmp(it->node().attribute("position").value(), "left") ) {
					insertword.adddtok(cctok);
					insertword.adddtok(cb);
				} else {
					insertword.adddtok(cb);
					insertword.adddtok(cctok);
				};
				wordParse->push_back(insertword);
			};
			if ( ccprob < 1 ) { partialclitic = 1; }; // if this is not always a clitic, force to search on
		};
	};
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
	insertword.setform(word);
	int totprob = 0;
	
	pugi::xpath_node_set tmp;
	string xpath;

	if ( verbose > 1 ) { cout << "** Processing new word: " << parseword.token.attribute("id").value() << " " << word << endl; };
	
	// step 1: see if we do not just know this word from the training corpus
	tmp = getLexProb(word); 
	if ( !tmp.empty() ) {
		if ( debug > 1 ) { cout << " - " << tmp.size() << " occurrence(s) found in training corpus " << endl; };
		for (pugi::xpath_node_set::const_iterator it = tmp.begin(); it != tmp.end(); ++it) {
			insertword.settag((*it).node().attribute("key").value());
			insertword.prob = atof((*it).node().attribute("cnt").value());
			insertword.lexitem = (*it).node();
			// When there are @nform like forms in the corpus, check if those match the lexicon or partially discard
			if ( checkfld != "" ) {
				string checkform = calcform(insertword.lexitem, checkfld); // we cannot do a simple check since there is no @form in the lexitem
				if ( debug > 4 ) { cout << "Check if " << checkfld << " matches: " << checkform << " , " << calcform(parseword.token, checkfld) << endl; };
				if ( checkform != "" && checkform != calcform(insertword.lexitem, "form") && strtolower(checkform) != strtolower(calcform(parseword.token, checkfld)) ) { // <tok> in the lexicon does not have a @form
					insertword.prob = insertword.prob / 1000; // divide by much to make it very unlikely but not impossible
					if ( debug > 2 ) { cout << checkfld << " does not match: " << checkform << " =/= " << calcform(parseword.token, checkfld) << endl;  insertword.lexitem.print(std::cout); };
				};
			}; 
			insertword.source = "corpus:" + NumberToString(tmp.size());
			if ( verbose > 2 ) { (*it).node().print(std::cout); };
			wordParse.push_back(insertword);
			totprob += insertword.prob;
		};
	};

	// step 1b: see if we do not know this word in lowercase from the training corpus
	if (wordParse.size() == 0) {
		tmp = getLexProb(strtolower(word)); 
		if ( !tmp.empty() ) {
			if ( debug > 1 ) { cout << " - " << tmp.size() << " lowercase occurrence(s) found in training corpus " << endl; };
			for (pugi::xpath_node_set::const_iterator it = tmp.begin(); it != tmp.end(); ++it) {
				insertword.settag((*it).node().attribute("key").value());
				insertword.prob = atof((*it).node().attribute("cnt").value());
				insertword.lexitem = (*it).node();
				insertword.source = "corpus:" + NumberToString(tmp.size());
				// When there are @nform like forms in the corpus, check if those match
				if ( checkfld != "" ) {
					if ( calcform(insertword.lexitem, checkfld) != calcform(parseword.token, checkfld) ) {
						insertword.prob = insertword.prob / 1000; // divide by much to make it very unlikely but not impossible
					};
				}; 
				if ( verbose > 2 ) { (*it).node().print(std::cout); };
				wordParse.push_back(insertword);
				totprob += insertword.prob;
			};
		};
	};	
	
	// step 2: see if it happens to be a clitic/contracted word
	// TODO: this does not work yet
	partialclitic = 0; // word that start with st which is sometimes a clitic should be treated as both
	if ( parameters.first_child().child("dtoks") != NULL && wordParse.size() == 0 ) { // && dtoksout
		wordtoken checkword; 
		checkword = insertword;
		clitic_check ( checkword, &wordParse);
		if ( debug > 3 ) { cout << "   Checked clitic on " << word << " : " << wordParse.size()  << " found " << endl; };
	};
	
	// step 3: see if it happens to be in the external lexicon
	// TODO: this should become XML as well probably
// 	if ( lexiconProbs[word].size() > 0  && ( wordParse.size() == 0 || lexsmooth > 0 ) ) {
// 		// word found in the external lexicon, copy to wordParses
// 		if ( debug > 1 ) { cout << " - found in lexicon " << lexiconProbs[word].size() << endl; };
// 		map<string,wordtoken>::iterator pos;
// 		for (pos = lexiconProbs[word].begin(); pos != lexiconProbs[word].end(); ++pos) {
// 			if ( debug > 2 ) { cout << "   -  " << pos->first << endl; };
// 			insertword = pos->second;
// 			insertword.id = parseword.id;
// 			insertword.setform(word);
// 			insertword.settag(pos->first);
// 			insertword.prob = 1;
// 			insertword.token = parseword.token;
// 			insertword.source = "lexicon";
// 			insertword.lemrule = "";
// 			wordParse.push_back(insertword);
//  			totprob += insertword.prob;
//  		};
// 	};
		
	// step 4: use the end of the word (should become also beginning) to determine POS
	// when have not yet found the word, or when we want to lexically smooth
	// or when we have a word that only optionally starts with a clitic
	// TODO: this no longer does smoothing - check if that is needed
	if ( wordParse.size() == 0 || lexsmooth || partialclitic ) {
		tmp = getEndProb(word); 
		if ( !tmp.empty() ) {
			pugi::xpath_node_set::const_iterator it = tmp.begin();
			string foundend = (*it).node().parent().attribute("key").value();
			if ( verbose > 1 ) { 
				cout << " - " << tmp.size() << " types(s) found in training corpus for ending " << foundend << endl; 
			};
			for (pugi::xpath_node_set::const_iterator it = tmp.begin(); it != tmp.end(); ++it) {
				insertword.settag((*it).node().attribute("key").value());
				insertword.prob = atof((*it).node().attribute("cnt").value());
				insertword.source = "ending:" + foundend;
				insertword.lexitem = (*it).node();
				insertword.lemmatize();
				if ( verbose > 2 ) { (*it).node().print(std::cout); };
				wordParse.push_back(insertword);
				totprob += insertword.prob;
			};
		};
	};
	
	// almost complete failure - try with raw POS frequencies 
	if ( wordParse.size() == 0 ) {
		if ( debug > 1 ) { cout << " - not found - defaulting to tag frequency " << endl; };
		xpath = "item";
		tmp = parameters.first_child().child("tags").select_nodes(xpath.c_str());
		if ( !tmp.empty() ) {
			if ( verbose > 3 ) { cout << " - defaulting to the " << tmp.size() << " tags in the tagset " << endl; };
			for (pugi::xpath_node_set::const_iterator it = tmp.begin(); it != tmp.end(); ++it) {
				insertword.settag((*it).node().attribute("key").value());
				insertword.prob = atof((*it).node().attribute("cnt").value());
				insertword.source = "tagset";
				if ( verbose > 2 ) { (*it).node().print(std::cout); };
				wordParse.push_back(insertword);
				totprob += insertword.prob;
			};
		};
	};

	// if all else fails, just produce unknown
	if ( wordParse.size() == 0 ) {
		if ( debug > 1 ) { cout << " - not found - throwing <unknown> " << endl; };
		insertword.settag("<unknown>");
		insertword.prob = 1;
		insertword.source = "<unknown>";
		wordParse.push_back(insertword);
		totprob += insertword.prob;
	};

	// now, normalize to prob [0,1]
	if ( totprob > 0 ) {
		if ( debug > 5 ) { cout << " - normalizing lexical probabilities to [0,1]" << endl; };
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
		cout << wordnr << ". "  << word << " - from input file: " << calcform(insertword.token,"form") << "/"  << calcform(insertword.token,checkfld) << "/" << insertword.token.attribute(tagpos.c_str()).value()  << "/" << insertword.token.attribute("lemma").value() << endl; 
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
	if ( debug > 1 ) { cout << " -- number of possible tags: " << wordParse.size() << endl; };
	
	// add the morphological option for this word to the path,
	// calculating the path likelihoods
	pathList.addword(wordParse);
	totpaths += pathList.pathlist.size();
	
	// sort the optimal paths by probability
	pathList.pathlist.sort();
	pathList.pathlist.reverse();

	// normalize path probabilities to [0,1]
	float totprob = 0;
	for (list<tokpath>::iterator it = pathList.pathlist.begin(); it != pathList.pathlist.end(); it++) {
		totprob += it->prob;
	};			
	for (list<tokpath>::iterator it = pathList.pathlist.begin(); it != pathList.pathlist.end(); it++) {
		it->prob = it->prob/totprob;
	};			
	
	// printout the current number of paths still under consideration
	if ( debug > 1 ) { cout << " -- number of (optimal) paths : " << pathList.size << endl; };
	if ( debug > 2 ) {
		for (list<tokpath>::iterator it = pathList.pathlist.begin(); it != pathList.pathlist.end(); it++) {
			cout << "    " << (*it).prob << " - " << (*it).str() << endl;
		};			
	};
};

// Deleted the READPARAMETERS
void parseparameters () {
	// Preload the posProbs - this might improve speed
	for ( pugi::xml_node lexitem = parameters.first_child().child("lexicon").child("item"); lexitem != NULL; lexitem = lexitem.next_sibling("dtok") ) {
		string word = lexitem.attribute("key").value();
		pugi::xpath_node_set tmp = lexitem.select_nodes("tok");
		posProbs[word] = tmp;
	};
	// Preload the transitionProbs
	// Preload the endProbs
};

void help() {
	cout << "Usage: neotagxml [OPTIONS] [FILE]" << endl;
	cout << "Tag FILE with Part-of-Speech tags, where FILE is an XML file" << endl << endl;
	cout << "Options:" << endl;
	cout << "  -?, --help\tThis help file" << endl;
	exit(1);
};

// Main 
int main (int argc, char * const argv[]) {

	// we somehow have to deal with UTF-8
    setlocale(LC_CTYPE, "UTF-8");
	// locale loc; //("en_UK.UTF-8");

	string word;
	string input_line;
	clock_t beginT, endT;
	
	taglog.append_child("neotag");
	tagsettings = taglog.first_child().append_child("settings");	
	tagstats = taglog.first_child().append_child("stats");	

	// Read language and text from commandline options
	int textid;             /* -t option */
	char foldername[50];
	char xmlfile[50] = "";
	char outfile[50] = "";
	
	beginT = clock(); time_t tm = time(0);
	taglog.first_child().append_attribute("starttime") = ctime(&tm); // .substr(0,ctime(&tm).length()-1);	
	
	// Read in all the command-line arguments
	for ( int i=1; i< argc; ++i ) {
		string argm = argv[i];
		
		if ( argm.substr(0,2) == "--" ) {
			int spacepos = argm.find("=");
			
			if ( spacepos == -1 ) {
				string akey = argm.substr(2);
				tagsettings.append_attribute(akey.c_str()) = "1";
				settings[akey] = "1";
			} else {
				string akey = argm.substr(2,spacepos-2);
				string aval = argm.substr(spacepos+1);
				settings[akey] = aval;
				tagsettings.append_attribute(akey.c_str()) = aval.c_str();
			};
		};		
	};

	// Some things we want as accessible variables
	if ( tagsettings.attribute("debug") != NULL ) { debug = atoi(tagsettings.attribute("debug").value()); verbose = true; };
	if ( tagsettings.attribute("test") != NULL ) { test = true; verbose = true; };
	if ( tagsettings.attribute("verbose") != NULL ) { verbose = true; };
	
	if ( tagsettings.attribute("xmlfile") == NULL ) {
        cout << "Usage: neotag --xmlfile=[fn] --params=[parameter folder]" << endl;
    	return -1;
	};
	
	// Read in the source XML file
    pugi::xml_document doc;
    if ( !doc.load_file(tagsettings.attribute("xmlfile").value(), (pugi::parse_ws_pcdata | pugi::parse_declaration | pugi::parse_doctype ) & ~pugi::parse_wconv_attribute & ~pugi::parse_escapes ) ) { // pugi::parse_default | 
        cout << "Failed to load XML file: " << tagsettings.attribute("featuretags").value() << endl;
    	return -1;
    };
	taglog.first_child().append_attribute("filename") = realpath(tagsettings.attribute("xmlfile").value(), NULL);

	// Deal with tagger options

	// Read the settings.xml file where appropriate - by default from ./Resources/settings.xml
	string settingsfile;
	if ( tagsettings.attribute("settings") != NULL ) { 
		settingsfile = tagsettings.attribute("settings").value();
	} else {
		settingsfile = "./Resources/settings.xml";
	};
	pugi::xml_document xmlsettings;
    if ( xmlsettings.load_file(settingsfile.c_str())) {
    	if ( verbose  ) { cout << "- Using settings from file " << settingsfile << endl;   }; 	
    };
    
    pugi::xml_node parameter;
	pattlist = xmlsettings.select_nodes("//neotag/parameters/item");
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it)
	{
		if ( verbose ) cout << "  XML checking against " << (*it).node().attribute("restriction").value() << endl;
		if ( (*it).node().attribute("restriction") == NULL 
				|| doc.select_single_node((*it).node().attribute("restriction").value()) != NULL ) {
			parameter = (*it).node();
			if ( verbose > 2 ) cout << "  Applicable parameters restriction: " << (*it).node().attribute("restriction").value() << endl;
		} else {
			if ( verbose > 2 ) cout << "  Non-applicable parameters restriction: " << (*it).node().attribute("restriction").value() << endl;
		};
	};

	// Place all neotag parameter settings from the settings.xml into the tagsettings
	for (pugi::xml_attribute_iterator it = parameter.attributes_begin(); it != parameter.attributes_end(); ++it)
	{
		if ( tagsettings.attribute((*it).name()) == NULL ) { 
			tagsettings.append_attribute((*it).name()) =  (*it).value();
		};
	};
	// Also take settings from the //neotag root ([item]/../..)
	for (pugi::xml_attribute_iterator it = parameter.parent().parent().attributes_begin(); it != parameter.parent().parent().attributes_end(); ++it)
	{
		if ( tagsettings.attribute((*it).name()) == NULL ) { 
			tagsettings.append_attribute((*it).name()) =  (*it).value();
		};
	};

	// See if we found a parameters folder to use - or throw an exception
	if ( !strcmp(tagsettings.attribute("params").value(), "") ) {
        cout << "No parameters folder indicate or none applicable found in settings file: " << settingsfile << endl;
		if ( verbose > 2 ) taglog.print(std::cout);
        return -1;
	} else {
		if ( !parameters.load_file(tagsettings.attribute("params").value(), (pugi::parse_ws_pcdata)) ) { // pugi::parse_default | 
			cout << "Failed to load parameters file: " << tagsettings.attribute("params").value() << endl;
			return -1;
		} else if ( verbose ) {
			cout << "- Using parameters file: " << tagsettings.attribute("params").value() << endl;
		};
	};
	
	// Should we preparse the XML?
	parseparameters();
	
	// Some default settings
	char tokxpath [50]; string tmp2;
	if ( tagsettings.attribute("tokxpath") != NULL ) { strcpy(tokxpath, tagsettings.attribute("tokxpath").value()); } 
		else { strcpy(tokxpath, "//tok"); };
	if ( tagsettings.attribute("tagform") != NULL ) { tagfld = tagsettings.attribute("tagform").value(); } 
		else { tagfld = "form"; };
	if ( tagsettings.attribute("checkform") != NULL ) { checkfld = tagsettings.attribute("checkform").value(); } 
		else { checkfld = ""; };
	if ( tagsettings.attribute("lemmatize") != NULL ) { lemmafld = tagsettings.attribute("lemmatize").value(); } 
		else { lemmafld = "form"; };
	if ( tagsettings.attribute("tagpos") != NULL ) { tagpos = tagsettings.attribute("tagpos").value(); } 
		else { tagpos = "pos"; };
	if ( tagsettings.attribute("formtags") != NULL ) { 
		tmp2 = tagsettings.attribute("formtags").value(); 
	} else { 
		tmp2 = "lemma,"+tagpos; // By default, tag for lemma and pos
	};
	split(formTags, tmp2, is_any_of(",")); 
	
	if ( tagsettings.attribute("help") != NULL ) { help(); };
	
	// These need to read a specific set of parameters!!
	if ( tagsettings.attribute("featuretags") != NULL  ) { featuretags = true; };
	if ( tagsettings.attribute("positiontags") != NULL  ) { positiontags = true; };
	if ( tagsettings.attribute("tagsrc") != NULL  ) { tagsrcshow = true; };
	if ( tagsettings.attribute("neologisms") != NULL  ) { neologisms = true; };
	if ( tagsettings.attribute("dtoksout").value() == NULL  ) { dtoksout = false; } else { dtoksout = true; };
		
	if ( tagsettings.attribute("endlen") != NULL ) { endlen = atoi(tagsettings.attribute("endlen").value()); };
	if ( tagsettings.attribute("homsmooth") != NULL ) { homsmooth = atof(tagsettings.attribute("homsmooth").value()); };
	if ( tagsettings.attribute("lexsmooth") != NULL ) { lexsmooth = atof(tagsettings.attribute("lexsmooth").value()); };
	if ( tagsettings.attribute("transsmooth") != NULL ) { transitionsmooth = atof(tagsettings.attribute("transsmooth").value()); };
	if ( tagsettings.attribute("transitionfactor") != NULL ) { transitionfactor = atof(tagsettings.attribute("transitionfactor").value()); };

	if ( tagsettings.attribute("contextfactor") != NULL ) { contextfactor = atof(tagsettings.attribute("contextfactor").value()); };
	if ( tagsettings.attribute("contextlength") != NULL ) { contextlength = atoi(tagsettings.attribute("contextlength").value()); } else contextlength = 1;

	if ( tagsettings.attribute("outfile") != NULL ) { strcpy(outfile, tagsettings.attribute("outfile").value()); };

	// Now we need to read the inheritance tree
	inherit["form"] = "pform"; // form ALWAYS inherits from pform
	pattlist = xmlsettings.select_nodes("//xmlfile//pattributes//item");
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it)
	{
		pugi::xpath_node xmlfnode = *it;		
		string patt = xmlfnode.node().attribute("key").value();
		if (xmlfnode.node().attribute("inherit")) { 
			inherit[patt] = xmlfnode.node().attribute("inherit").value();
		};
	};

	
	outstream = &std::cout;
	tofile = false;

	char tmp [50];
	pugi::xml_node ttrain = taglog.first_child().append_child("training_corpus");	
	sprintf(tmp, "%d", int(stats["traintokens"]));  ttrain.append_attribute("tok_size") = tmp;
	sprintf(tmp, "%d", int(stats["lexcount"])); ttrain.append_attribute("lex_size") = tmp;
	sprintf(tmp, "%d", (int) tagset.size()); ttrain.append_attribute("tagset_size") = tmp;
	sprintf(tmp, "%d", int(stats["lexiconcount"])); ttrain.append_attribute("lexicon_size") = tmp;
	// if ( featuretags ) { sprintf(tmp, "%d", int(stats["dictcount"])); ttrain.append_attribute("dict_size") = tmp; };
	sprintf(tmp, "%f", (float(clock())-float(beginT))/float(CLOCKS_PER_SEC)); ttrain.append_attribute("loadtime") = tmp;
		
	sprintf(tmp, "%f", (float) lexsmooth); tagsettings.append_attribute("lexsmooth") = tmp;
	sprintf(tmp, "%f", (float) transitionsmooth); tagsettings.append_attribute("transitionsmooth") = tmp;
	sprintf(tmp, "%f", (float) transitionfactor); tagsettings.append_attribute("transitionfactor") = tmp;
	sprintf(tmp, "%d", (int) endlen); tagsettings.append_attribute("endlen") = tmp;


	// If running in debug mode, output data about the training corpus
	if ( debug  ) {
		printf("-------------------------------\n");
		printf("---- PARAMETER FILES READ -----\n");
		printf("----    configurations:   -----\n");
		printf("-------------------------------\n");
		printf("-------------------------------\n");
		printf("---        ANALYSIS        ----\n");
		printf("-------------------------------\n");
	};
	endT = clock();
	
	// keep reading <tok> from filename (XML)
	// parse each word in turn calculating all optimal paths
	// printout whenever an unambiguous state is reached
	pugi::xpath_node_set toks = pugi::xpath_query(tokxpath).evaluate_node_set(doc);
	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it) {
		
		pugi::xpath_node node = *it;
		pugi::xml_node token = node.node();

		wordtoken insertword;

		insertword.id = token.attribute("id").value(); 

		// Determine which form to tag on
		// which can be inherited
		insertword.form = calcform(node.node(), tagfld);
		
		if ( insertword.form == "--" ) { continue; }; // Ignore words explicitly set to NULL 
		
		insertword.token = token;
		
		if ( insertword.form.size() == 0 ) { continue; }; // ignore empty lines		
		
		treatWord ( insertword );

	};
	pathList.best().print();

	sprintf(tmp, "%d", (int) wordnr); tagstats.append_attribute("tokcnt") = tmp;
	sprintf(tmp, "%f", (float(clock())-float(endT))/float(CLOCKS_PER_SEC)); tagstats.append_attribute("tagtime") = tmp;
	sprintf(tmp, "%f", (float) int(totparses)/int(wordnr)); tagstats.append_attribute("tokamb") = tmp;
	sprintf(tmp, "%d", (int) totparses); tagstats.append_attribute("totparses") = tmp;
	sprintf(tmp, "%f", (float) int(totlexparses)/int(lexnr)); tagstats.append_attribute("vocamb") = tmp;
	sprintf(tmp, "%f", (float) (int(wordnr)-int(lexnr))/float(lexnr)); tagstats.append_attribute("oov") = tmp;

	// when running in verbose mode, output data about the analysis
	if ( debug || verbose ) { 
		float elapsed = (float(clock())-float(endT))/float(CLOCKS_PER_SEC);
		float elapsed2 = (float(clock())-float(beginT))/float(CLOCKS_PER_SEC);
		if ( debug >1 || verbose >1) { cout << "------------------" << endl << "-- done parsing --" << endl << "------------------" << endl; }
		else {  cout << "--------------------------" << endl; };
				
		cout << wordnr << " tokens tagged in " << elapsed << " (" 
			<< wordnr/elapsed << " tk/s) - total time " << elapsed2 << endl; 
		
		if ( verbose > 2 ) {
			cout << "average word ambiguity: " << float(totparses)/float(wordnr) << endl;
			cout << "average voc word ambiguity: " << float(totlexparses)/float(lexnr) << endl;
			cout << "average path ambiguity: " << float(totpaths)/float(wordnr) << endl;
			cout << "in-lexicon items: " << 100*(float(lexnr)/float(wordnr)) << "%" << endl;
		};
	};

	// when running in verbose test mode, output data about the accuracy
	if ( test && stats["taggood"] ) {
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

	if ( *outfile == 0 && test ) {
		doc.print(std::cout, "", pugi::format_raw, pugi::encoding_utf8); // , "\t", pugi::format_raw, pugi::encoding_utf8);
	} else {
		if ( !strcmp(outfile, "") ) { strcpy(outfile, tagsettings.attribute("xmlfile").value()); };
		if ( verbose > 1 ) { cout << "Saving to: " << outfile << endl; };
		doc.save_file(outfile, "", ( pugi::format_raw | pugi::format_no_escapes ) ); // , pugi::encoding_utf8);
	};
	
	taglog.save_file("neotag.log");
		
    return 0;
	
}

