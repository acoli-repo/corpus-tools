#include <iostream>
#include <sstream>
#include <string.h>
#include <stdio.h>
#include <fstream>
#include <map>
#include <vector>
#include <dirent.h>
#include <sys/stat.h>
#include "pugixml.hpp"
#include "functions.hpp"

using namespace std;

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

string tagfld; // The field to use for tagging
string tagpos; // 
string lemmafld;
string tagattlist;
vector<string> formTags;
vector<string> lemTags;
int contextlength = 1;
int endlen = 4;
string dtokform;
int tokcnt;

vector<string> tagHist;
   
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
	// while ( tagHist.size() > contextlength + 1 ) { tagHist.pop_front();  }
	while ( tagHist.size() > contextlength + 1 ) { tagHist.erase(tagHist.begin());  }
	string histstring = join(tagHist, ".");
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

string calctag ( pugi::xml_node node ) {
	string tagtag;
	string tagdlm; 
	tagdlm = "#"; // TODO: Make the tag delimiter optional? (is only tagger-internal)
	if ( tagpos.find(tagdlm) != -1 ) {
		// we need to concatenate several attributes
		stringstream ss(tagpos);
		string item; string delim;
		delim = ""; tagtag = ""; 
		int parts = 0;
		while (getline(ss, item, tagdlm[0])) {
			tagtag = tagtag + delim + node.attribute(item.c_str()).value();
			parts++;
			delim = tagdlm;
		};	
		// If we have no content, return empty string
		if ( tagtag.size() == parts - 1 ) { tagtag = ""; };
    } else {
		// a simple tag attribute - just return it
		tagtag = node.attribute(tagpos.c_str()).value();
	};
	return tagtag;
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
	
	// Add tags for all dtoks (separated by a dot)
	sep =  "";
	for ( pugi::xml_node dtoken = node.node().child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") ) {
		string tmp3 =  calctag(dtoken); 
		tagtag = tagtag + sep + tmp3;
		sep = ".";
	}; 
	if ( tagtag == "" ) {
		// Calculate the tag for this node if there are no dtoks
		tagtag = calctag(node.node()); 
	};

	if ( debug > 1 ) node.node().print(std::cout);

	if ( tagform == "--" || tagform == "" ) { 
		if ( debug > 1 ) { cout << "Skipping - empty string: " << getfld << endl; };
		return;
	};

	// If the tag consists only of dots, the word has not been tagged
	// string tagtest = tagtag; tagtest.erase(std::remove(tagtest.begin(), tagtest.end(), '.'), tagtest.end());
	if ( preg_match(tagtag, "^[.]*$") && tagsettings.attribute("inclnotag") == NULL ) { 
		if ( debug > 1 ) { cout << "Skipping - not tagged, no: " <<  tagpos << endl; };
		return;
	} else { 
		if ( debug > 3 ) { cout << "Tag: " <<  tagtag << endl; };
	};
	
	
	// We have a valid token - handle it
	tokcnt++;

	if ( lexitems[tagform][tagtag] ) {
		// We should check if this is the same word
		tok = lexitems[tagform][tagtag];
		if ( debug > 6 ) { cout << "Existing word: "; tok.print(std::cout); };
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
		tok.append_attribute("key") = tagtag.c_str(); 
		tok.append_attribute("cnt") = 1; 
		// add all items that are relevant here
		if ( debug > 6 ) { cout << "New word: " << tagform << endl;  };
		for (vector<string>::iterator it2 = formTags.begin(); it2 != formTags.end(); it2++) {
			string t = *it2;
			if ( debug > 8 ) { cout << "Adding feature: " << t << endl;  };
			if ( node.node().attribute(t.c_str()) != NULL ) tok.append_attribute(t.c_str()) = node.node().attribute(t.c_str()).value();
		}

	   
	    // Add lemma-level attributes
	    // TODO: implement
	   	if ( lemTags.size() ) {
	   	};

	   	// Add dtoks to the item
	   	// TODO: make this copy only the attributes that are to be tagged
	   	// TODO: ignore the dtoks (from this example) if there are nforms in the dtok
        for ( pugi::xml_node dtoken = node.node().child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") )
        {
			pugi::xml_node dtok = tok.append_child("dtok");
			for (vector<string>::iterator it2 = formTags.begin(); it2 != formTags.end(); it2++) {
				string t = *it2;
				if ( dtoken.attribute(t.c_str()) != NULL ) dtok.append_attribute(t.c_str()) = dtoken.attribute(t.c_str()).value();
			}
			// If we are not tagging from form, make sure the <dtok> has a form
			if ( tagfld != "form" && ( dtok.attribute("form") == NULL || !strcmp(dtok.attribute("form").value(), "") ) ) {
				string dform = calcform(dtoken, tagfld);
				if ( debug > 1 ) { cout << "Adding @form for <dtok> from " << tagfld << " : " <<  dform << endl; };
				dtok.append_attribute("form") = dform.c_str();
			};
        }

		lexitems[tagform][""] = lexitem;
		if ( tagtag != "" ) {
			lexitems[tagform][tagtag] = tok;
		};

		if (debug > 4) {
			cout << "Resulting new item: ";
			lexitem.print(std::cout);
		};
	
	   
	};

	if (debug > 1) {
		cout << "New/updated item: ";
		tok.print(std::cout);
	}; 
		
	
	// push back tagtag onto tagHist
	if ( !node.node().child("dtok").empty() ) {
        for ( pugi::xml_node dtoken = node.node().child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") ) {
			addhist(dtoken.attribute(tagpos.c_str()).value());
		};
	} else {
		addhist(tagtag);
	};
};

bool utf8_is_valid(const string& string)
{
    int c,i,ix,n,j;
    for (i=0, ix=string.length(); i < ix; i++)
    {
        c = (unsigned char) string[i];
        //if (c==0x09 || c==0x0a || c==0x0d || (0x20 <= c && c <= 0x7e) ) n = 0; // is_printable_ascii
        if (0x00 <= c && c <= 0x7f) n=0; // 0bbbbbbb
        else if ((c & 0xE0) == 0xC0) n=1; // 110bbbbb
        else if ( c==0xed && i<(ix-1) && ((unsigned char)string[i+1] & 0xa0)==0xa0) return false; //U+d800 to U+dfff
        else if ((c & 0xF0) == 0xE0) n=2; // 1110bbbb
        else if ((c & 0xF8) == 0xF0) n=3; // 11110bbb
        //else if (($c & 0xFC) == 0xF8) n=4; // 111110bb //byte 5, unnecessary in 4 byte UTF-8
        //else if (($c & 0xFE) == 0xFC) n=5; // 1111110b //byte 6, unnecessary in 4 byte UTF-8
        else return false;
        for (j=0; j<n && i<ix; j++) { // n bytes matching 10bbbbbb follow ?
            if ((++i == ix) || (( (unsigned char)string[i] & 0xC0) != 0x80))
                return false;
        }
    }
    return true;
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

// Determine the main pos
string getmainpos ( pugi::xml_node tok ) { 
	string pos = tok.attribute(tagpos.c_str()).value();
	return pos.substr(0,1);
};

// Check whether mainpos is an open-class type (adjective, noun, verb, adverb)
bool positiontags = true;
string pos_type ( string mainpos ) {
	// TODO make this more reasonable
	if ( positiontags ) {
		if ( mainpos == "A" || mainpos == "N" || mainpos == "V" || mainpos == "E" ) { 
			return "open"; 
		};
		if ( mainpos == "F" || mainpos == "X" || mainpos == "Z" ) { 
			return "nonword"; 
		};
		return "closed"; 
	};

	return "unk";
};

// Check whether a character is a punctuation mark 
bool is_punct ( string str ) {
	// TODO make this UTF8 [:isPunct]
	if ( str == "'" || str == "-" ) { return true; };

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
			if ( ( *(wrdroot.substr(wrdidx,1).c_str()) & 0xc0 ) == 0x80 ) {  wrdidx++; };
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
	if ( debug > 4 ) { cout << "Lemmatization rule: " << wrd << " + " << lmma << " = " <<  wrdroot + '#' + lemroot << endl; };
	if ( !utf8_is_valid(wrdroot) || !utf8_is_valid(lemroot) ) { 
		if ( debug > 4 ) { cout << "Killed: not UTF8" << wrdroot + '#' + lemroot << endl; };
		return "";
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

	if ( debug > 5 ) { cout << "Checking file restriction: " << tagsettings.attribute("restriction").value() << endl; };

	if ( tagsettings.attribute("restriction") != NULL 
			&& doc.select_node(tagsettings.attribute("restriction").value()) == NULL ) {
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

template <typename T> string tostr(const T& t) { 
   ostringstream os; 
   os<<t; 
   return os.str(); 
} 

int treatdir (string dirname) {
    struct dirent *entry;
    DIR *dp;
 
 	if ( debug > 5 ) { cout << "Treating dir: " << dirname << endl; };

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

	pugi::xml_node paramsettings = paramfile.first_child().append_child("settings");
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

	if ( tagsettings.attribute("help") != NULL ) {
		cout << "Usage: neotagtrain [options] xmlfile" << endl << endl;
		cout << "Optional arguments:" << endl;
		cout << "--help            show this help message and exit" << endl;
		cout << "--verbose         run in verbose mode" << endl;
		cout << "--settings        location of the settings file" << endl;
		return -1;
	};
	
	// Some things we want as accessible variables
	if ( tagsettings.attribute("debug") != NULL ) { debug = atoi(tagsettings.attribute("debug").value()); };
	if ( tagsettings.attribute("test") != NULL ) { test = true; verbose = true; };
	if ( tagsettings.attribute("verbose") != NULL ) { verbose = true; };

	if ( tagsettings.attribute("version") != NULL ) { 
		cout << "neotagtrain version 1.0" << endl;
		return -1; 
	};

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
		// TODO: This should be stored for multiple parameter folders
		if ( (*it).node().attribute("training") != NULL && ( tagsettings.attribute("pid") == NULL || !strcmp(tagsettings.attribute("pid").value(), (*it).node().attribute("pid").value()) ) ) {
			parameters = (*it).node();
		} else {
			if ( verbose ) {
				cout << "- Skipping parameters: " << (*it).node().attribute("restriction").value();
				if ( tagsettings.attribute("pid") != NULL ) cout << " - wanted: " <<  tagsettings.attribute("pid").value();
				cout << endl; 
			};
		};
	};
	
	if ( parameters == NULL ) {
		cout << "- Selected parameters not found: " << tagsettings.attribute("pid").value() << endl;
		return -1;
	} else if ( verbose ) {
		cout << "- Using parameters: " << parameters.attribute("pid").value() << " - restriction: " << parameters.attribute("restriction").value() << endl;
		// paramfile.first_child().append_attribute("restriction") = parameters.attribute("restriction").value();	
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
		tagattlist= tagsettings.attribute("formtags").value(); 
	} else { 
		tagattlist = "lemma,"+tagpos; // By default, tag for lemma and pos
	};
	if ( debug ) { cout << "Tagging forms: " << tagattlist << endl; };
	formTags = split(tagattlist, ","); 
		for (vector<string>::iterator it2 = formTags.begin(); it2 != formTags.end(); it2++) {
			string t = *it2;
			if ( debug > 8 ) { cout << "Feature to tag: " << t << endl;  };
		};

	if ( tagsettings.attribute("dtokform") != NULL ) { 
		dtokform = tagsettings.attribute("wordfld").value();
	} else {
		dtokform = "dtokform"; // By default, use @form for the word
	}

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
    	vector<string> doar = split(dofolders, " ");
		for (vector<string>::iterator it2 = doar.begin(); it2 != doar.end(); it2++) {
			string fldr = *it2;
			if ( debug ) {
				cout << "  - Analyzing files from: " << fldr << endl;    	
			};
			treatdir ( fldr );
		}
	} else {
		if ( verbose ) cout << "- Training folder: ./xmlfiles" << endl;
		treatdir ( "xmlfiles" ); 
	};

	if ( verbose ) { cout << "Calculating from lexicon" << endl; };

	// Now that we have our full lexicon, start counting the classes
	if ( verbose ) {
		cout << "Counting class frequencies" << endl;
	};
	pugi::xml_node tagset;
	tagset = paramfile.first_child().append_child("tags");
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
		
	};

	// Add the dtok lexicon
	if ( verbose ) {
		cout << "Creating a lexicon of productive clitics" << endl;
	};
	pugi::xml_node dtoklex;
	map<string, map<string, pugi::xml_node> > dtoks; // keep track of the existing dtoks
	map<string, int> ignored; // Keep track of lexical items we ignored since those should be ignored in the counts as well
	dtoklex = paramfile.first_child().append_child("dtoks");
	pattlist = lexicon.select_nodes("//lexicon//tok[dtok]");
	string position; string dtag; string dform;
	for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it) {
		int oc1 = -1; int oc2 = -1; int i = -1; int cc = -1;
		pugi::xml_node token = (*it).node();
		string tokform = token.parent().attribute("key").value();
		bool nonword = false;
		if ( debug > 3 ) { 
			cout << " - Checking productive dtoks on: " << tokform << endl;
		};

		// Skip productive dtoks if the token is normalized (works only if nform is one of the tagforms)
		if ( token.attribute("nform") != NULL && tagfld != "nform" ) { 
			if ( debug > 3 ) { 
				cout << " - Skipping token with normalized orthography: " << tokform << " = " << token.attribute("nform").value() << endl;
			};
			continue; 
		};

		vector<pugi::xml_node> dtoklist; string tokpos = "";
		// First determine where, if at all, the open classed dtok(s) are
        for ( pugi::xml_node dtoken = (*it).node().child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") ) {
        	i++;
        	dtoklist.push_back(dtoken);
        	string dtmainpos = getmainpos(dtoken);
        	tokpos = tokpos + dtmainpos;
        	if ( pos_type(dtmainpos) == "nonword" ) { 
        		nonword = true;
        	};
        	if ( pos_type(dtmainpos) == "open" ) { 
        		if ( oc1 == -1 ) { oc1 = i; };
        		oc2 = i;
        	} else { 
        		cc++; 
        	};
		};
		
		if ( nonword ) { 
			if ( debug > 3 ) { 
				cout << " - Skipping token with non-word parts: " << tokform << " = " << tokpos << endl;
			};
			continue; 
		};
		
		// store the part to the left of oc1
		if ( oc1 > 0 ) {
			//determine the position of the oc1
	        pugi::xml_node octoken = dtoklist.at(oc1);
	        pugi::xml_node proddtok;
	        string ocform;
	        ocform = octoken.attribute("formpart").value();
	        if ( ocform == "" ) { ocform = calcform(octoken, tagfld); };
	        int ocpos = tokform.find(ocform);
	        if ( ocpos != string::npos && ocpos > 0 && ocform.length() > 0 ) {

				string formpart = tokform.substr(0, ocpos);
				if ( debug > 3 ) { 
					cout << " - occurrence of oc1 = " << ocform << ": " << formpart << endl;
				};
			
				// gather the form parts
				dtag = ""; dform = ""; 
				string sep = ""; proddtok.set_name("item");
				for ( int i=0; i<oc1; i++ ) {
					pugi::xml_node dtoken = dtoklist.at(i);
					dtag += sep; sep = ".";
					dtag += dtoken.attribute(tagpos.c_str()).value();
					proddtok.append_copy(dtoken);
				};

				pugi::xml_node dtokfrm = dtoks[formpart][""];
				if ( dtag != "" && formpart != "" )  {
					if ( dtokfrm == NULL ) {
						dtokfrm = dtoklex.append_child("item");
						dtokfrm.append_attribute("key") = formpart.c_str();
						dtokfrm.append_attribute("lexcnt") = 0;
						dtokfrm.append_attribute("position") = "left";
						dtoks[formpart][""] = dtokfrm;
					};
					pugi::xml_node dtokitm = dtoks[formpart][dtag];
					if ( dtokitm == NULL ) {
						dtokitm = dtokfrm.append_child("item");
						dtokitm.append_attribute("key") = dtag.c_str();
						dtokitm.append_attribute("cnt") = 0;
						dtoks[formpart][dtag] = dtokitm;
						for ( int i=0; i<oc1; i++ ) {
							pugi::xml_node dtoken = dtoklist.at(i);
							dtag += sep; sep = ".";
							dtag += dtoken.attribute(tagpos.c_str()).value();
							dtokitm.append_copy(dtoken);
						};
					};
				
					dtokitm.attribute("cnt") = atoi(dtokitm.attribute("cnt").value()) + 1; // atoi((*it).node().attribute("cnt").value());
					if ( debug >2 ) { dtokitm.print(std::cout); };
				};

			} else {
				ignored[tokform] = 1;
				if ( debug > 0 ) { 
					cout << " - ignoring dtok part - " << ocform << " not found in " << tokform << endl;
				};
			};
		};

		if ( oc2 > -1 && oc2 < dtoklist.size() ) {
			//determine the position of the oc1
	        pugi::xml_node octoken = dtoklist.at(oc2);
	        pugi::xml_node proddtok;
	        string ocform;
	        ocform = octoken.attribute("formpart").value();
	        if ( ocform == "" ) { ocform = calcform(octoken, tagfld); };
	        int ocpos = tokform.find(ocform);
	        if ( ocpos != string::npos && ocpos < tokform.length()-1  && ocform.length() > 0 ) {
	        	ocpos += ocform.length();

				string formpart = tokform.substr(ocpos);
				if ( debug > 3 ) { 
					cout << " - occurrence of oc2 = " << ocform << ": " << formpart << endl;
				};
			
				// gather the form parts
				dtag = ""; dform = ""; 
				string sep = ""; proddtok.set_name("item");
				for ( int i=oc1+1; i<dtoklist.size(); i++ ) {
					pugi::xml_node dtoken = dtoklist.at(i);
					dtag += sep; sep = ".";
					dtag += dtoken.attribute(tagpos.c_str()).value();
					proddtok.append_copy(dtoken);
				};

				pugi::xml_node dtokfrm = dtoks[formpart][""];
				if ( dtag != ""  && formpart != "" )  {
					if ( dtokfrm == NULL ) {
						dtokfrm = dtoklex.append_child("item");
						dtokfrm.append_attribute("key") = formpart.c_str();
						dtokfrm.append_attribute("lexcnt") = 0;
						dtokfrm.append_attribute("position") = "right";
						dtoks[formpart][""] = dtokfrm;
					};
					pugi::xml_node dtokitm = dtoks[formpart][dtag];
					if ( dtokitm == NULL ) {
						dtokitm = dtokfrm.append_child("item");
						dtokitm.append_attribute("key") = dtag.c_str();
						dtokitm.append_attribute("cnt") = 0;
						dtoks[formpart][dtag] = dtokitm;
						for ( int i=oc2+1; i<dtoklist.size(); i++ ) {
							pugi::xml_node dtoken = dtoklist.at(i);
							dtag += sep; sep = ".";
							dtag += dtoken.attribute(tagpos.c_str()).value();
							dtokitm.append_copy(dtoken);
						};
					};
				
					dtokitm.attribute("cnt") = atoi(dtokitm.attribute("cnt").value()) + 1; // atoi((*it).node().attribute("cnt").value());
					if ( debug >2 ) { dtokitm.print(std::cout); };
				};

			} else {
				ignored[tokform] = 1;
				if ( debug > 0 ) { 
					cout << " - ignoring dtok part - " << ocform << " not found in " << tokform << endl;
				};
			};
		};
		
				
	};

	// Now count the relative frequency of each productive dtok
	pattlist = lexicon.select_nodes("//lexicon/item");
	pugi::xpath_node_set dti = lexicon.select_nodes("//dtoks/item/item");
	if ( verbose ) {
		cout << "Checking productivity of clitics" << endl;
	};
	// Count how many occurrence of words ending on this clitic there are
	for( map<string, map<string, pugi::xml_node> >::const_iterator it2 = dtoks.begin(); it2 != dtoks.end(); ++it2 ) {
		map<string, pugi::xml_node> tmp = it2->second;
		pugi::xml_node dnode = tmp[""];
		string dform = dnode.child("dtok").attribute("form").value();
		string dtokform = dnode.attribute("key").value();
		string pos = dnode.attribute("position").value();
		if ( dnode.attribute("lexcnt") == NULL ) { dnode.append_attribute("lexcnt"); };
		for (pugi::xpath_node_set::const_iterator it = pattlist.begin(); it != pattlist.end(); ++it) {
			string tokform = it->node().attribute("key").value();
	    	string check;
	    	if ( dtokform.length() > tokform.length() || ignored[tokform] == 1 ) {
	    		check = "";
	    	} else if ( pos == "left" ) {
	    		check = tokform.substr(0,dtokform.length());
	    	} else  {
	    		check = tokform.substr(tokform.length()-dtokform.length());
	    	};
			if ( check == dtokform ) {
				pugi::xpath_node_set tmp = it->node().select_nodes("tok"); 
				int tagcnt = tmp.size();
				dnode.attribute("lexcnt") = atoi(dnode.attribute("lexcnt").value()) + tagcnt;
			};
	    };
	    int count = 0;
		for (pugi::xpath_node_set::const_iterator it = dti.begin(); it != dti.end(); ++it) {
			string clitform = it->node().parent().attribute("key").value();
	    	string check;
	    	if ( dtokform.length() > clitform.length() && dtokform.length() > 0 ) {
	    		check = "";
	    	} else if ( pos == "left" ) {
	    		check = clitform.substr(0,dtokform.length());
	    	} else  {
	    		check = clitform.substr(clitform.length()-dtokform.length());
	    	};
			if ( check == dtokform ) {
				if ( debug > 2 ) { cout << dtokform << " part of " << clitform << " >> " << count << endl; };
				count = count + atoi(it->node().attribute("cnt").value());
			};
		};
		if ( atoi(dnode.attribute("lexcnt").value()) > 0 ) {
			float clitprob = (float)count/atoi(dnode.attribute("lexcnt").value());
			if  ( clitprob > 1 ) { clitprob = 1; }; // Just a safety measure
			if ( dnode.attribute("clitprob") == NULL ) { dnode.append_attribute("clitprob"); };
			dnode.attribute("clitprob") = clitprob;
			if ( debug > 1 ) { cout << "setting prob for " << dtokform << " to " << count << "/" << atoi(dnode.attribute("lexcnt").value()) << " = " << clitprob << endl; };
		} else {
			if ( debug > 1 ) { cout << "skipping " << dtokform << " : lexcnt = " << atoi(dnode.attribute("lexcnt").value()) << endl; };
		};
	};
		
	// copy the relevant tagsettings to the parameter file
    for (pugi::xml_attribute_iterator ait = tagsettings.attributes_begin(); ait != tagsettings.attributes_end(); ++ait) {
		if ( strcmp(ait->name(), "debug") && strcmp(ait->name(), "verbose") && strcmp(ait->name(), "folder") && strcmp(ait->name(), "params") && strcmp(ait->name(), "training") && strcmp(ait->name(), "pid") && strcmp(ait->name(), "log") ) {
			paramsettings.append_attribute(ait->name()) = ait->value();
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
