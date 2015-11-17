#include <boost/algorithm/string.hpp>
#include <boost/tokenizer.hpp>
#include <boost/foreach.hpp>
#include "pugixml.hpp"
#include <iostream>
#include <string.h>
#include <stdio.h>
#include <fstream>
#include <map>
#include <vector>
#include <dirent.h>
#include <sys/stat.h>

using namespace std;
using namespace boost;

int debug = 0;
bool test = false;
bool verbose = false;

char tokxpath [50];
pugi::xml_document trainlog;
pugi::xml_node tagsettings;
pugi::xml_node trainstats;

pugi::xml_document paramfile;
pugi::xml_node lexicon;
pugi::xml_node transitions;

string tagfld;
string tagpos;
string lemmafld;
list<string> formTags;
list<string> lemTags;
int contextlength = 1;
int endlen = 4;
int tokcnt;

list<string> tagHist;
   
map<string, map<string, pugi::xml_node> > lexitems; // lexiconprob items
map<string,pugi::xml_node > transs; // transition items

// Variables in which we store the preferences
pugi::xpath_node_set pattlist; 
vector <string> cqpatts;
pugi::xml_document xmlsettings;
map < string, string > inherit;

string formcase ( string form ) {
	string wcase;
	if ( form.size() > 0 ) {
		if ( isupper(form.at(0)) && isupper(form.at(form.size()-1)) && form.size() > 1 ) { wcase = "UU"; } 
		else if ( isupper(form.at(0)) ) { wcase = "Ul"; } 
		else if ( islower(form.at(0)) ) { wcase = "ll"; } 
		else { wcase = "??"; };
	} else { wcase = "??"; };
	return wcase;
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


void addhist (string tagkey) {
	tagHist.push_back(tagkey);
	while ( tagHist.size() > contextlength + 1 ) { tagHist.pop_front(); }
	string histstring = boost::algorithm::join(tagHist, ".");
		if ( debug > 2 ) { cout << "Transition history: " <<  histstring << endl; };
	pugi::xml_node transitem;
	if ( transs[histstring] ) {
		transitem = transs[histstring];
		transitem.attribute("cnt") = atoi(transitem.attribute("cnt").value()) + 1;
	} else {
		transitem = transitions.append_child("item");
		transitem.append_attribute("key") = histstring.c_str();
		transitem.append_attribute("cnt") = "1";
	};
	transs[histstring] = transitem;
};

void treatnode ( pugi::xpath_node node ) {
	string sep = "";
	vector<string>::iterator cait;
	pugi::xml_node lexitem;
	pugi::xml_node tok;
	
	// Determine which form to tag on
	// go up the tree if necessary
	string getfld = tagfld;
	string tagform; string tagtag;
	while ( !node.node().attribute(getfld.c_str()) && inherit[getfld] != "" ) {
		getfld = inherit[getfld];
	};
	if ( getfld == "pform" ) {
		tagform = node.node().child_value(); // This works, since <tok> always have a @form when innerXML
	} else {
		tagform = node.node().attribute(getfld.c_str()).value();
	};
	tagtag = node.node().attribute(tagpos.c_str()).value();

	if ( debug > 1 ) node.node().print(std::cout);

	if ( tagform == "--" || tagform == "" ) { 
		if ( debug > 1 ) { cout << "Skipping - empty string: " << getfld << endl; };
		return;
	};

	if ( tagtag == "" && tagsettings.attribute("inclnotag") == NULL && node.node().child("dtok") == NULL ) { 
		if ( debug > 1 ) { cout << "Skipping - not tagged, no: " <<  tagpos << endl; };
		return;
	};
	
	// We have a valid token - handle it
	tokcnt++;

	if ( lexitems[tagform][tagtag] ) {
		// We should check if this is the same word
		tok = lexitems[tagform][tagtag];
		tok.attribute("cnt") = atoi(tok.attribute("cnt").value()) + 1;
	} else {
		// new word		
		if ( lexitems[tagform][""] ) {
			lexitem = lexitems[tagform][""];
		} else{ 
			lexitem = lexicon.append_child("item");
			lexitem.append_attribute("key") = tagform.c_str();
		};
		tok = lexitem.append_child("tok");
		tok.append_attribute("key") = node.node().attribute(tagpos.c_str()).value(); 
		tok.append_attribute("cnt") = 1; 
		// add all items that are relevant here
		BOOST_FOREACH(string t, formTags )
		{
			if ( node.node().attribute(t.c_str()) != NULL ) tok.append_attribute(t.c_str()) = node.node().attribute(t.c_str()).value();
		}

		lexitems[tagform][""] = lexitem;
		lexitems[tagform][tagtag] = tok;
	   
	    // Add lemma-level attributes
	   	if ( lemTags.size() ) {
			BOOST_FOREACH(string t, lemTags )
			{
			}
	   	};
	   	
	   	// Add dtoks to the item
        for ( pugi::xml_node dtoken = node.node().child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") )
        {
			pugi::xml_node dtok = tok.append_child("dtok");
				string tmp1 = tok.attribute("key").value();
				string tmp2 =  ".";
				string tmp3 =  dtoken.attribute(tagpos.c_str()).value(); 
				string tmp4 = tmp1 + tmp2 + tmp3;
				tok.attribute("key") = tmp4.c_str();
			for (pugi::xml_attribute attr = dtoken.first_attribute(); attr; attr = attr.next_attribute())
			{
				if ( strcmp(attr.name(), "id") ) dtok.append_attribute(attr.name()) = attr.value();
			}        
        }
	   
	};
		
	if (debug > 1) lexitem.print(std::cout);

	// push back tagtag onto tagHist
	if ( tok.child("dtok") != NULL ) {
        for ( pugi::xml_node dtoken = node.node().child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") ) {
			addhist(dtoken.attribute(tagpos.c_str()).value());
		};
	} else {
		addhist(tagtag);
	};
};

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

// Determine the main pos
string getmainpos ( pugi::xml_node tok ) { 
	string pos = tok.attribute(tagpos.c_str()).value();
	return pos.substr(0,1);
};

// Check whether mainpos is an open-class type (adjective, noun, verb, adverb)
bool is_open_class ( string mainpos ) {
	// TODO make this actually reasonable
	if ( mainpos == "ADJ" || mainpos == "A" || mainpos == "N" || mainpos == "V" ) { return true; };

	return false;
};

// Create a rule to build the lemma from the wordform
// This is being training on the training lexicon 
// and optionally the external full-form lexicon
string lemrulemake ( string wrd, string lmma ) {
	// this works, minus that it sometimes matches the wrong chars
	// heren/heer = **r*n#**r instead of ***en#**e*
	// aangetroffen/aantreffen = ***ge**off**#******ffe* instead of ***ge**o****#*****e****
	// these should stay, but lead to multiple options
	
	// Match as many characters between lemma and form as possible
	int wrdidx=0; int lemidx = 0;
	string wrdroot = wrd; string lemroot = lmma; 
	// find each char of lemidx in turn in the lemma
	while ( lemidx < lemroot.size() ) {
		// walk through the form until we find a match for the lemchar
		while ( wrdroot[wrdidx] != lemroot[lemidx] && tolower(wrdroot[wrdidx]) != tolower(lemroot[lemidx]) && wrdidx < wrdroot.size() ) {
			wrdidx++;
		};
		if ( wrdidx < wrdroot.size() ) { // match found
			// Rewind a character if this is the first part of a UTF char
			// if ( ( *(wrdroot[wrdidx]) & 0xc0 ) == 0x80 ) { 
			//	wrdroot[wrdidx+1] = '*'; 	lemroot[lemidx+1] = '*'; 	
			// } else {
				wrdroot[wrdidx] = '*'; 	lemroot[lemidx] = '*'; 	
			// };
		} else { // no match found - rewind to just after the last * in the form and skip a lemchar
			while ( wrdroot[wrdidx] != '*' && wrdidx > 0 ) { wrdidx--; };
			wrdidx++;
		};
		lemidx++;
	};
	
	if ( wrdroot.size() == 0 || lemroot.size() == 0 ) {
		return "";
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
	return lemrule;
};

void treatfile ( string filename ) {

	if ( debug ) { cout << "Treating: " << filename << endl; };
	if ( filename.find(".xml") == -1 ) { return; }; // We can only treat XML files and assume those always end on .xml

    // Now - read the file 
	string sep;

    pugi::xml_document doc;
    if (!doc.load_file(filename.c_str())) {
        cout << "  Failed to load XML file " << filename << endl;
    	return;
    };

	pugi::xpath_node resnode;

	if ( tagsettings.attribute("restriction") != NULL 
			&& doc.select_single_node(tagsettings.attribute("restriction").value()) == NULL ) {
		if ( debug ) cout << "- XML " << filename << " not matching " << tagsettings.attribute("restriction") .value() << endl;
		return;
	};

	char tokxpath [50];
	if ( tagsettings.attribute("tokxpath") != NULL ) { strcpy(tokxpath, tagsettings.attribute("tokxpath").value()); } 
		else { strcpy(tokxpath, "//tok"); };
	    
    // Go through the toks
	pugi::xpath_node_set toks = doc.select_nodes(tokxpath);

	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xpath_node node = *it;

		treatnode(node);
	}

};


int treatdir (string dirname) {
    struct dirent *entry;
    DIR *dp;
 
 	// We need kill any *.xml at the end, since we are treating folders, not a glob
	string patt = "*";
	if ( dirname.find(patt) !=std::string::npos ) {
		if ( debug ) { 
			cout << "  Cleanup: removing everything after * in " << dirname << endl;
		};
		dirname = dirname.substr(0,dirname.find(patt));
   	};
   	
	const char *path = dirname.c_str();


    dp = opendir(path);
    if (dp == NULL) {
    	if ( debug ) {
    		cout << "  Failed to open dir: " << path << endl;
    	};
        return -1;
    }

   struct stat st;
   while ((entry = readdir(dp))) {
		string ffname = dirname + '/' + string(entry->d_name);
		lstat(ffname.c_str(), &st);
		if(S_ISDIR(st.st_mode)) {
		   if ( entry->d_name[0] != '.' ) { 
		   	  treatdir(ffname); 
		   };
		} else if(S_ISLNK(st.st_mode)) {
		  treatdir(ffname); // We assume links always to be links to directories
		} else {
		   treatfile(ffname);
		};
    };

    closedir(dp);
    return 0;
};

int main(int argc, char *argv[])
{


	trainlog.append_child("xmltrain");
	tagsettings = trainlog.first_child().append_child("settings");	
	trainstats = trainlog.first_child().append_child("stats");	

	paramfile.append_child("neotag");
	lexicon = paramfile.first_child().append_child("lexicon");
	transitions = paramfile.first_child().append_child("transitions");

	time_t beginT = clock(); time_t tm = time(0);
	string tmp = ctime(&tm);
	paramfile.first_child().append_attribute("created") = tmp.substr(0,tmp.length()-1).c_str();	
	trainlog.first_child().append_attribute("starttime") = tmp.substr(0,tmp.length()-1).c_str();	

	// Read in all the command-line arguments
	for ( int i=1; i< argc; ++i ) {
		string argm = argv[i];
		
		if ( argm.substr(0,2) == "--" ) {
			int spacepos = argm.find("=");
			
			if ( spacepos == -1 ) {
				string akey = argm.substr(2);
				tagsettings.append_attribute(akey.c_str()) = "1";
			} else {
				string akey = argm.substr(2,spacepos-2);
				string aval = argm.substr(spacepos+1);
				tagsettings.append_attribute(akey.c_str()) = aval.c_str();
			};
		};		
	};

	// Some things we want as accessible variables
	if ( tagsettings.attribute("debug") != NULL ) { debug = true; };
	if ( tagsettings.attribute("test") != NULL ) { test = true; verbose = true; };
	if ( tagsettings.attribute("verbose") != NULL ) { verbose = true; };

	if ( tagsettings.attribute("contextlength") != NULL ) { contextlength = atoi(tagsettings.attribute("contextlength").value()); } else contextlength = 1;
	if ( tagsettings.attribute("endlen") != NULL ) { endlen = atoi(tagsettings.attribute("endlen").value()); };
	
	// Read the settings.xml file where appropriate - by default from ./Resources/settings.xml
	string settingsfile;
	string folder;
	if ( tagsettings.attribute("settings") != NULL ) { 
		settingsfile = tagsettings.attribute("settings").value();
	} else {
		folder = ".";
		settingsfile = "./Resources/settings.xml";
	};
	pugi::xml_document xmlsettings;
    if ( xmlsettings.load_file(settingsfile.c_str())) {
    	if ( verbose ) { cout << "- Using settings from " << settingsfile << endl;   }; 	
    };
    
    pugi::xml_node parameters;
	pattlist = xmlsettings.select_nodes("//neotag/parameters/item");
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it)
	{
		// This should be stored for multiple parameter folders
		if ( (*it).node().attribute("training") != NULL && ( tagsettings.attribute("pid") == NULL || !strcmp(tagsettings.attribute("pid").value(), (*it).node().attribute("pid").value()) ) ) {
			parameters = (*it).node();
		} else {
			if ( verbose ) cout << "- Skipping parameters: " << (*it).node().attribute("restriction").value() << endl;
		};
	};
	
	if ( parameters == NULL ) {
		cout << "- Selected parameters not found: " << tagsettings.attribute("pid").value() << endl;
		return -1;
	} else if ( verbose ) {
		cout << "- Using parameters: " << parameters.attribute("pid").value() << " - restriction: " << parameters.attribute("restriction").value() << endl;
		paramfile.first_child().append_attribute("restriction") = parameters.attribute("restriction").value();	
	};

	// Place all neotag parameter settings from the settings.xml into the tagsettings
	for (pugi::xml_attribute_iterator it = parameters.attributes_begin(); it != parameters.attributes_end(); ++it)
	{
		if ( tagsettings.attribute((*it).name()) == NULL ) { 
			tagsettings.append_attribute((*it).name()) =  (*it).value();
		};
	};
	// Also take settings from the //neotag root ([item]/../..)
	for (pugi::xml_attribute_iterator it = parameters.parent().parent().attributes_begin(); it != parameters.parent().parent().attributes_end(); ++it)
	{
		if ( tagsettings.attribute((*it).name()) == NULL ) { 
			tagsettings.append_attribute((*it).name()) =  (*it).value();
		};
	};
	
	// Determine which field to tag from and on
	tmp = "";
	if ( tagsettings.attribute("tagform") != NULL ) { tagfld = tagsettings.attribute("tagform").value(); } 
		else { tagfld = "form"; };
	if ( tagsettings.attribute("tagpos") != NULL ) { tagpos = tagsettings.attribute("tagpos").value(); } 
		else { tagpos = "pos"; };
	if ( tagsettings.attribute("lemmatize") != NULL ) { lemmafld = tagsettings.attribute("lemmatize").value(); } 
		else { lemmafld = "form"; };
	if ( tagsettings.attribute("formtags") != NULL ) { 
		tmp= tagsettings.attribute("formtags").value(); 
	} else { 
		tmp = "lemma,"+tagpos; // By default, tag for lemma and pos
	};
	split(formTags, tmp, is_any_of(",")); 

	string sep;

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
	
	string dofolders = tagsettings.attribute("training").value();
	if ( dofolders != "" ) {
		if ( verbose ) cout << "- Training folder(s): " << dofolders << endl;
		char_separator<char> sep(" ");
    	tokenizer< char_separator<char> > tokens(dofolders, sep);
		BOOST_FOREACH (const string& fldr, tokens) {
			if ( debug ) {
				cout << "  - Analyzing files from: " << fldr << endl;    	
			};
			treatdir ( fldr );
		}
	} else {
		if ( verbose ) cout << "- Training folder: ./xmlfiles" << endl;
		treatdir ( "xmlfiles" ); 
	};

	if ( verbose ) { cout << "- Calculating additional data" << endl; };

	// Now that we have our full lexicon, start counting the classes
	pugi::xml_node tagset;
	tagset = paramfile.first_child().append_child("tags");
	pugi::xml_node wordendxml;
	wordendxml = paramfile.first_child().append_child("endings");
	map<string, map<string, pugi::xml_node> > wordends; // wordend items
	map<string, map<string, map<string, pugi::xml_node> > > lemruless; // wordend items
	pattlist = lexicon.select_nodes("//tok[not(dtok)]");
	map<string, map<string, pugi::xml_node> > tagss; // wordend items
	string wcase;
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it) {
		string word = (*it).node().parent().attribute("key").value(); int mblen = 0;
		wcase = formcase(word);
		
		// Add to the tagset
		string tagstring = (*it).node().attribute("key").value();
		pugi::xml_node tagsitem;
		pugi::xml_node tagscase;
		if ( tagss[tagstring][""] ) {
			tagsitem = tagss[tagstring][""];
			tagsitem.attribute("cnt") = atoi(tagsitem.attribute("cnt").value()) + atoi((*it).node().attribute("cnt").value());
			if ( tagss[tagstring][wcase] ) {
				tagscase = tagss[tagstring][wcase];
				tagscase.attribute("cnt") = atoi(tagscase.attribute("cnt").value()) + atoi((*it).node().attribute("cnt").value());
			} else {
				tagscase = tagsitem.append_child("case");
				tagscase.append_attribute("key") = wcase.c_str();
				tagscase.append_attribute("cnt") = (*it).node().attribute("cnt").value();
				tagss[tagstring][wcase] = tagscase;
			};
		} else {
			tagsitem = tagset.append_child("item");
			tagsitem.append_attribute("key") = tagstring.c_str();
			tagsitem.append_attribute("cnt") = (*it).node().attribute("cnt").value();
			tagss[tagstring][""] = tagsitem;
			tagscase = tagsitem.append_child("case");
			tagscase.append_attribute("key") = wcase.c_str();
			tagscase.append_attribute("cnt") = (*it).node().attribute("cnt").value();
			tagss[tagstring][wcase] = tagscase;
		};
		
		// Add to the ending probabilities
		pugi::xml_node lexitem;
		pugi::xml_node tok;
		pugi::xml_node lemrules;
		string lemrule = lemrulemake(calcform((*it).node(), lemmafld), (*it).node().attribute("lemma").value());
		if ( debug > 4 ) { cout << calcform((*it).node(), lemmafld) << " / " << (*it).node().attribute("lemma").value() << " => " << lemrule << endl; };
		for ( int i = 1; i <= (endlen+mblen); i++ ) {
			if ( word.length() > i ) {
				string wordend = word.substr(word.length()-i, word.length());
				// Do not count parts of MB chars
				if ( ( *(wordend.substr(0,1).c_str()) & 0xc0 ) == 0x80 ) {  mblen++; continue; };
				if ( wordends[wordend][tagstring] ) {
					// Add to the existing word ending
					// We should check if this is the same word
					tok = wordends[wordend][tagstring];
					tok.attribute("cnt") = atoi(tok.attribute("cnt").value()) + atoi((*it).node().attribute("cnt").value());
					// add the lemrule
					if ( lemrule.length() > 0 ) {
						if ( lemruless[wordend][tagstring][lemrule] ) {
							lemrules = lemruless[wordend][tagstring][lemrule];
							lemrules.attribute("cnt") = atoi(lemrules.attribute("cnt").value()) + atoi((*it).node().attribute("cnt").value());
						} else {
							lemrules = tok.append_child("lemmatization");
							lemrules.append_attribute("key") = lemrule.c_str();
							lemrules.append_attribute("cnt") = (*it).node().attribute("cnt").value(); 
						};
					};
				} else {
					// new word		
					if ( wordends[wordend][""] ) {
						lexitem = wordends[wordend][""];
					} else{ 
						lexitem = wordendxml.append_child("item");
						lexitem.append_attribute("key") = wordend.c_str();
					};
					tok = lexitem.append_child("item");
					tok.append_attribute("key") = tagstring.c_str(); 
					tok.append_attribute("cnt") = (*it).node().attribute("cnt").value(); 
					// add the lemrule
					if ( lemrule.length() > 0 ) {
						lemrules = tok.append_child("lemmatization");
						lemrules.append_attribute("key") = lemrule.c_str();
						lemrules.append_attribute("cnt") = (*it).node().attribute("cnt").value(); 
					};
				};
				wordends[wordend][""] = lexitem;
				wordends[wordend][tagstring] = tok;
				lemruless[wordend][tagstring][lemrule] = lemrules;
			};
		};
		
	};

	// Add the dtok lexicon
	pugi::xml_node dtoklex;
	dtoklex = paramfile.first_child().append_child("dtoks");
	pugi::xml_node dtokitm;
	map<string, map<string, pugi::xml_node> > dtoks; // keep track of the existing dtoks
	pattlist = lexicon.select_nodes("//tok[dtok]");
	string position;
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it) {
		int oc1 = -1; int oc2 = -1; int i = -1; int cc = -1;
		vector<pugi::xml_node> dtoklist;
		// First determine where, if at all, the open classed dtok(s) are
        for ( pugi::xml_node dtoken = (*it).node().child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") ) {
        	i++;
        	dtoklist.push_back(dtoken);
        	string dtmainpos = getmainpos(dtoken);
        	if ( is_open_class(dtmainpos) ) { 
        		if ( oc1 == -1 ) { oc1 = i; };
        		oc2 = i;
        	} else { cc++; };
		};
		if ( oc1 == -1 || cc == -1 ) { 
			continue; 
		}; // With no open-class dtok, this is a lexicalized contraction
			
		// Now go through the dtoks again and check if they are to be added
        for ( int i=0; i < dtoklist.size(); i++ ) {
        	pugi::xml_node dtoken = dtoklist.at(i);
			if ( dtoken.attribute("form") == NULL ) { continue; }; // We cannot do anything with dtoks that do not have a form
			string dform = calcform(dtoken, tagfld);
        	// TODO: the @nform of the dtok is not always part of the @nform of the tok - resolution?
			string dtag = dtoken.attribute(tagpos.c_str()).value();
			// Determine the position and the left/right context - skip when not is_open_class
			string sibform; string sibpos;
			if ( i<oc1 && dtoklist.at(i+1) ) { 
				position = "left"; 
				sibpos = dtoklist.at(i+1).attribute(tagpos.c_str()).value();
				sibform = calcform(dtoklist.at(i+1), tagfld);
			} else if ( i>oc2 && dtoklist.at(i-1) ) { 
				position = "right"; 
				sibpos = dtoklist.at(i-1).attribute(tagpos.c_str()).value();
				sibform = calcform(dtoklist.at(i-1), tagfld);
			} else { continue; };
			
			// Store in the XML if we reached a (potentially) productive dtok
			string dtpf = dform + '.' + dtag + '.' + position;
			if ( dtoks[dtpf][""] ) {
				dtokitm = dtoks[dtpf][""];
				dtokitm.attribute("cnt") = atoi(dtokitm.attribute("cnt").value()) + 1; // atoi((*it).node().attribute("cnt").value());
				if ( dtoks[dtpf][sibpos] ) {
					pugi::xml_node sibling = dtoks[dtpf][sibpos];
					sibling.attribute("cnt") = atoi(sibling.attribute("cnt").value()) + 1; // atoi((*it).node().attribute("cnt").value());
					string cfrm = sibling.attribute("form").value();
					if ( cfrm.size() > 0 && cfrm != sibform ) {
						if ( position == "right" ) {
							int j = 0; 
							while ( cfrm.substr(j,1) == sibform.substr(j,1) && j<cfrm.size() ) { j++; };
							cfrm = cfrm.substr(0,j);
						} else {
							int j = 1; 
							while ( cfrm.substr(cfrm.size()-j,1) == sibform.substr(sibform.size()-j,1) 
								&& j<cfrm.size() 
								&& j<sibform.size() ) { j++; };
							cfrm = cfrm.substr(j, cfrm.size()-j);
 						};
						sibling.attribute("form") = cfrm.c_str();
					};
				} else {
					pugi::xml_node sibling = dtokitm.append_child("sibling");
						sibling.append_attribute("key") = sibpos.c_str();
						sibling.append_attribute("form") = sibform.c_str();
						sibling.append_attribute("cnt") = 1; // (*it).node().attribute("cnt").value();
					dtoks[dtpf][sibpos] = sibling;
				};
			} else {
				dtokitm = dtoklex.append_child("dtok");
				dtokitm.append_attribute("key") = dtpf.c_str();
				dtokitm.append_attribute("form") = dform.c_str();
				dtokitm.append_attribute("lemma") = dtoken.attribute("lemma").value();
				dtokitm.append_attribute(tagpos.c_str()) = dtag.c_str();
				dtokitm.append_attribute("position") = position.c_str();
				dtokitm.append_attribute("cnt") = 1; // (*it).node().attribute("cnt").value();
				pugi::xml_node sibling = dtokitm.append_child("sibling");
					sibling.append_attribute("key") = sibpos.c_str();
					sibling.append_attribute("form") = sibform.c_str();
					sibling.append_attribute("cnt") = 1; // (*it).node().attribute("cnt").value();
				if ( dtoken.attribute("fform") != NULL ) { dtokitm.append_attribute("fform") = dtoken.attribute("fform").value(); };
				dtoks[dtpf][""] = dtokitm;
				dtoks[dtpf][sibpos] = sibling;
			};
			if ( debug >2 ) { dtokitm.print(std::cout); };
		};		
		
				
	};
	
	// clean up the dtokparts
	pattlist = lexicon.select_nodes("//dtoks/dtok/sibling");
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it) {
		if ( !strcmp(it->node().attribute("cnt").value(), "1")  || !strcmp(it->node().attribute("form").value(), "") ) { 
			it->node().remove_attribute("form"); 
		};
	};

	if ( verbose ) cout << "- " << tokcnt << " tokens in training corpus" << endl; 
	trainstats.append_attribute("tokens") = tokcnt;
	paramfile.first_child().append_attribute("cnt") = tokcnt;
	
	if ( tagsettings.attribute("log") != NULL ) {
		cout << "- Saving log to: " << tagsettings.attribute("log").value() << endl;
		trainlog.save_file(tagsettings.attribute("log").value());
	};
	if ( test ) {
		paramfile.print(std::cout);	
	} else {
		if ( verbose ) cout << "- Saving parameter file to: " << tagsettings.attribute("params").value() << endl; 
		paramfile.save_file(tagsettings.attribute("params").value());	
	};
}
