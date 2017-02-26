#include <boost/algorithm/string.hpp>
#include <boost/tokenizer.hpp>
#include <boost/foreach.hpp>
#include "pugixml.hpp"
#include <iostream>
#include <sstream>  
#include <stdio.h>
#include <fstream>
#include <map>
#include <vector>
#include <dirent.h>
#include <sys/stat.h>
#include <arpa/inet.h>

using namespace std;
using namespace boost;

int debug = 0;
bool test = false;
bool verbose = false;

char tokxpath [50];
pugi::xml_document trainlog;
pugi::xml_node cqpsettings;
pugi::xml_node cqpstats;
list<string> formTags;

    pugi::xml_document doc;

string wordfld; // field to use for the "word" attribute
string lemmafld;
string filename;
string corpusfolder;
string registryfolder;


int tokcnt = 0;

list<string> tagHist;
   
map<string, map<string, int> > lexitems; // .lexicon ids
map<string, map<string, ofstream*> > streams; // ascii output files
map<string, map<string, FILE*> > files; // real output files
map<string,int > lexidx; // max lexitems id
map<string,int > lexpos; // pos in the lexicon file

map<string,pugi::xml_document*> externals; // external XML files 

// Variables in which we store the preferences
pugi::xpath_node_set pattlist; 
vector <string> cqpatts;
pugi::xml_document xmlsettings;
map < string, string > inherit;

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

// Write CWB network style
void write_network_number ( int towrite, FILE *stream ) {
	int i = htonl(towrite);
	fwrite(&i, 4, 1, stream);
};

// Write a range to .rng
void write_range ( int pos1, int pos2, string tagname ) {
	if ( pos2 < pos1 ) { 
		if ( debug > 0 ) { cout << "negative range - not writing" << endl; };
		return; 
	}; // We can never have negative ranges
	
	if ( files[tagname]["rng"] == NULL ) {
		cout << "fatal error: no range file for rng of " << tagname << " - probably no <text> level defined in settings.xml" << endl;
	};
	write_network_number(pos1, files[tagname]["rng"]);
	write_network_number(pos2, files[tagname]["rng"]);
};

// Write a range to .rng, .avs, .avx
void write_range_value ( int pos1, int pos2, string tagname, string attname, string formval ) {
	if ( pos2 < pos1 ) { return; }; // We can never have negative ranges
	
	string formkey = tagname + "_" + attname;
	
	if ( files[formkey]["rng"] == NULL ) {
		cout << "fatal error: no range file for rng of " << formkey << endl;
	};
	write_network_number(pos1, files[formkey]["rng"]);
	write_network_number(pos2, files[formkey]["rng"]);

	if ( lexitems[formkey].size() == 0 ) { lexpos[formkey] = 0; lexidx[formkey] = 0;  }; // Initialize values 
	
	// CWB does not like empty value - convert to underscore
	if ( formval == "" ) { formval = "_"; };

	if ( debug > 2 ) { cout << "Range: " << formkey << " " << pos1 << "-" << pos2 << "  = " << formval << endl; };
	if ( lexitems[formkey].find(formval) == lexitems[formkey].end() ) {
		// new value		
		int thispos = lexpos[formkey];
		lexitems[formkey][formval] = thispos;
		if ( debug > 4 ) { cout << "New AVS value for " << formval << " = " << lexitems[formkey][formval] << " - " << thispos << endl; };
		*streams[formkey]["avs"] << formval << '\0'; 	streams[formkey]["avs"]->flush();
		lexpos[formkey] += formval.length() + 1;
	};
	write_network_number(lexidx[formkey], files[formkey]["avx"]);
	write_network_number(lexitems[formkey][formval], files[formkey]["avx"]);
	lexidx[formkey]++; 
	
};

void treatnode ( pugi::xpath_node node ) {
	string sep = "";
	vector<string>::iterator cait;
	pugi::xml_node lexitem;
	pugi::xml_node tok;
	
	if ( debug > 3	 ) node.node().print(std::cout);

	if ( node.node().attribute("id") == NULL ) { 
		if ( debug > 1 ) { cout << "Skipping - node without an ID: " << node.node().attribute("id").value() << endl; };
		return;
	};

	if ( !strcmp( node.node().attribute(wordfld.c_str()).value(), "--" ) ) { 
		if ( debug > 1 ) { cout << "Skipping - empty value for " << wordfld <<  ": " << node.node().attribute("id").value() << endl; };
		return;
	};

	// We have a valid token - handle it
	tokcnt++;
	if ( debug > 1 ) { cout << "Token " << tokcnt << " : " << node.node().attribute("id").value()  << " = " << calcform(node.node(), wordfld) << endl; };

	// Write the .lexicon, .lexicon.idx and .corpus files
	string formkey; string formval; pugi::xpath_node xres;
    for ( pugi::xml_node formfld = xmlsettings.first_child().child("cqp").child("pattributes").child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
		string xpath = formfld.attribute("xpath").value();
		formkey = formfld.attribute("key").value(); 
		if ( xpath != "" ) {
			if ( debug > 4 ) { cout << "Calculating XPath value for node: " << xpath << endl; };
			 xres = node.node().select_single_node(xpath.c_str());
			if ( debug > 4 ) { xres.node().print(cout); };
			if ( xres.attribute() ) {	
				formval = xres.attribute().value();
			} else {
				formval = xres.node().child_value();
			};
		} else {
			if ( formkey == "word" ) { // for "word" use the tag field
				formval = calcform(node.node(), wordfld);
			} else {
				formval = calcform(node.node(), formkey);
			};
		};
			
		if ( debug > 4 ) { cout << "Value: " << formkey << " = " << formval << endl; };
		if ( !lexitems[formkey][formval] ) {
			// new word		
			if ( debug > 4 ) { cout << formval << ":" << formkey << " - new value" << endl; };
			lexitems[formkey][formval] = lexidx[formkey];
			*streams[formkey]["lexicon"] << formval << '\0'; streams[formkey]["lexicon"]->flush();
			write_network_number(lexpos[formkey], files[formkey]["idx"]);
			lexidx[formkey]++; lexpos[formkey] += formval.length() + 1;
		};
		write_network_number(lexitems[formkey][formval], files[formkey]["corpus"]);

	};		

	// Write the word.xidx.rng
	int xmlpos1 = node.node().offset_debug()-1;
	std::ostringstream oss;
	node.node().print(oss);
	std::string xmltxt = oss.str();	
	int xmlpos2 = xmlpos1 + xmltxt.length(); 
	write_network_number(xmlpos1, files["xidx"]["rng"]);
	write_network_number(xmlpos2, files["xidx"]["rng"]);

	// write the //text/@id to text_id.idx
	write_network_number(lexidx["text_id"], files["text_id"]["idx"]);

};

void treatfile ( string filename ) {

	if ( filename.find(".xml") == -1 ) { 
        if ( debug > 0 ) { cout << "  Skipping non-XML file: " << filename << endl; };
		return; 
	}; // We can only treat XML files and assume those always end on .xml

    // Now - read the file 
	string sep;

    if (!doc.load_file(filename.c_str())) {
        cout << "  Failed to load XML file " << filename << endl;
    	return;
    };
    
    string fileid = filename.substr(filename.find_last_of("/")+1,filename.find_last_of(".")-filename.find_last_of("/")-1	);

	if ( debug > 0 ) { cout << "  - Treating: " << filename << " (" << fileid << ")" << endl; };

	pugi::xpath_node resnode;

	if ( cqpsettings.attribute("restriction") != NULL 
			&& doc.select_single_node(cqpsettings.attribute("restriction").value()) == NULL ) {
		if ( debug ) cout << "- XML " << filename << " not matching " << cqpsettings.attribute("restriction") .value() << endl;
		return;
	};

	char tokxpath [50]; string toktype;
	if ( cqpsettings.attribute("toktype") != NULL ) {
		toktype = cqpsettings.attribute("toktype").value();
	} else {
		toktype = "mtd";
	};
	
	if ( cqpsettings.attribute("tokxpath") != NULL ) { 
		strcpy(tokxpath, cqpsettings.attribute("tokxpath").value()); 
	} else {
		strcpy(tokxpath, "//tok"); 
	};
	if ( debug > 1 ) cout << "- treating all: " << tokxpath << " - toktype: " << toktype << endl;
	
    // Go through the toks
	pugi::xpath_node_set toks = doc.select_nodes(tokxpath);
	map<string, int> id_pos;

	int pos1 = tokcnt;
	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xml_node node = it->node();

		// If we have an enclosing <mtok>, use that one (when using mtoks)
		// TODO: this currently only looks at the direct parent
		if ( toktype.find("m") != std::string::npos && !strcmp(node.parent().name(), "mtok") ) {
			if ( id_pos.find(node.parent().attribute("id").value()) == id_pos.end() ) {
				// use the mtok instead of this tok
				if ( debug > 3 ) { cout << "Using mtok: " << node.parent().attribute("id").value() << " instead of " << node.attribute("id").value() << endl; };
				node = node.parent();
			} else {
				// we have already done this mtok
				if ( debug > 3 ) { cout << "Skipping mtok: " << node.parent().attribute("id").value() << " for " << node.attribute("id").value() <<  " (already done)" << endl; };
				continue;
			};
		};

		// If we have child <dtok>, use that one (when using mtoks)
		if ( toktype.find("d") != std::string::npos && node.child("dtok") ) {
			id_pos[node.attribute("id").value()] = tokcnt; // Use the first <dtok> as ref for the whole <tok> for stand-off purposes
	        for ( pugi::xml_node dtoken = node.child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") ) {
				id_pos[dtoken.attribute("id").value()] = tokcnt;
		
				treatnode(dtoken);

	    	};
		} else {
			id_pos[node.attribute("id").value()] = tokcnt;
		
			treatnode(node);
		};			
	}
	int pos2 = tokcnt-1;

	string idname;
	idname = filename;
	
	// Add the default attributes for <text>
	if ( debug > 0 ) {
		cout << "<text> " << idname << " ranging from " << pos1 << " to " << pos2 << endl; 
	};

	write_range (pos1, pos2, "text" );
		if ( debug > 4 ) { cout << "  - written to text" << endl; };
	write_range_value (pos1, pos2, "text", "id", idname);
		if ( debug > 4 ) { cout << "  - written to text_id" << endl; };

	// add the sattributes for all levels
	string formkey; string formval; 
	string rel_tokxpath = tokxpath;
	// TODO: Make this make a proper relative XPath, since //tok//dtok would currently become .//tok.//dtok
	replace_all(rel_tokxpath, "//", ".//");
		if ( debug > 4 ) { cout << "  - looking for the tokens inside this range: " << rel_tokxpath << endl; };
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("sattributes").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
		string taglvl = taglevel.attribute("level").value();
		if ( taglvl.length() == 0 ) { taglvl = tagname; };
		if ( taglvl == "text" ) {
			// This is the <text> level
			if ( !(pos2>pos1) ) { continue; }; // This will crash on texts without any tokens inside; do not add to CQP for now (but they should be added as indexes)
			for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
				formkey = formfld.attribute("key").value(); 
				if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
				formval = ""; // empty values
				string external = formfld.attribute("external").value();
				string xpath = formfld.attribute("xpath").value();
				if ( xpath != "" ) {
					pugi::xpath_node xres;
					if ( external != "" ) {
						if ( debug > 2 ) { cout << "External XML lookup: " << external << endl; };
						string tmp = doc.select_single_node(external.c_str()).attribute().value();
						if ( debug > 3 ) { cout << " -- lookup value: " << tmp << endl; };
						  vector <string> exval; 
						  // Initialize srings
						  split( exval, tmp, is_any_of( "#" ) );
						string exfile = exval[0];
						if ( exfile.substr(exfile.length()-4) == ".xml" && externals[exfile] == NULL ) { 
							exfile = "Resources/" + exfile;
							if ( verbose ) { cout << "Loading external XML file: " << exfile << " < " << tmp << endl; };
							externals[exval[0]] = new pugi::xml_document();
							externals[exval[0]]->load_file(exfile.c_str());
						};
						if ( exfile.substr(exfile.length()-4) != ".xml" && debug > 0 ) {
							cout << "Invalid external lookup: " << tmp << endl; 
						};
						if ( externals[exval[0]] != NULL ) { 
							if ( exval.size() > 1 ) {
								string idlookup = "//*[@id=\""+exval[1]+"\"]";
								if ( debug > 1 ) { cout << "ID lookup: " << idlookup << endl; };
								pugi::xml_node exnode;
								try {
									exnode = externals[exval[0]]->select_single_node(idlookup.c_str()).node();
								} catch(pugi::xpath_exception& e) { if ( debug > 4 ) { cout << "XPath error" << endl; };  };
								if ( debug > 2 ) { exnode.print(cout); };
								if ( exnode ) {
									xres = exnode.select_single_node(xpath.c_str()); 
								};
							} else {
								try {
									xres = externals[exval[0]]->first_child().select_single_node(xpath.c_str()); 
								} catch(pugi::xpath_exception& e) { if ( debug > 4 ) { cout << "XPath error" << endl; };  };
							};
						};
					} else {
						 xres = doc.select_single_node(xpath.c_str());
					};
					if ( xres.attribute() ) {	
						formval = xres.attribute().value();
					} else {
						formval = xres.node().child_value();
					};
				};

				write_range_value (pos1, pos2, "text", formkey, formval);
			};	
		} else {
			// Add non-text level attributes
			string xpath = "//text//" + taglvl;
			if ( debug > 2 ) { cout << "Looking for " << taglvl  << " = " << xpath << endl; };
			// Loop through the actual items
			pugi::xpath_node_set elmres = doc.select_nodes(xpath.c_str());
			for (pugi::xpath_node_set::const_iterator it = elmres.begin(); it != elmres.end(); ++it) {
				string tmpxpath;
				if ( taglvl == "tok[dtok]" ) { 
					tmpxpath = "dtok";
				} else {
					tmpxpath = rel_tokxpath;
				}
				if ( debug > 4 ) { cout << " - Relative xpath: " << tmpxpath << endl; };
				pugi::xpath_node_set rel_toks = it->node().select_nodes(tmpxpath.c_str());
				if ( rel_toks.empty() ) { continue; };
				string toka = rel_toks[0].node().attribute("id").value();
				string tokb = rel_toks[rel_toks.size()-1].node().attribute("id").value();
				int posa = id_pos[toka]; // first "token" in the range
				int posb = id_pos[tokb]; // last "token" in the range
				if ( debug > 2 ) { cout << " Found a range " << tagname << " " << it->node().attribute("id").value() << " from " << toka << " (" << posa << ") to " << tokb << " (" << posb << ")" << endl; };

				write_range(posa, posb, tagname );

				// Write the XXX_xidx.rng
				int xmlpos1 = it->node().offset_debug()-1;
				// std::ostringstream oss;
				// it->node().print(oss); // This is the interpreted XML, which is too long... get beginning of next node instead
				// std::string xmltxt = oss.str();	
				// int xmlpos2 = xmlpos1 + xmltxt.length(); 
				int xmlpos2 = it->node().select_single_node("./following::*").node().offset_debug()-1;
				if ( debug > 4 ) { cout << "Writing XIDX for " << tagname << " = " << xmlpos1 << " - " << xmlpos2 << endl; };
				write_network_number(xmlpos1, files[tagname + "_xidx"]["rng"]);
				write_network_number(xmlpos2, files[tagname + "_xidx"]["rng"]);

				for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
					formkey = formfld.attribute("key").value(); 
					if ( debug > 4 ) { cout << " - Looking for: " << formkey << endl; };
					if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
					formval = "";
					xpath = formfld.attribute("xpath").value();
					if ( xpath != "" ) {
						pugi::xpath_node xres;
						xres = it->node().select_single_node(xpath.c_str());
						if ( xres.attribute() ) {	
							formval = xres.attribute().value();
						} else {
							formval = xres.node().child_value();
						};
					} else if ( !strcmp(formfld.attribute("type").value(), "form") ) {
						// calculate the form for form-type tags (on mtok and tok[dtok])
						formval = calcform(it->node(), formkey);
						if ( debug > 3 ) { cout << " -- calculating form for " << tagname << " - " << formkey << " = " << formval << endl; };
					} else { 
						formval = it->node().attribute(formfld.attribute("key").value()).value();
					};
					
					
					// write the actual data
					write_range_value (posa, posb, tagname, formkey, formval);

				};
			};	

		};
	};

	// add the stand-off annotations
	if ( xmlsettings.first_child().child("cqp").child("annotations") ) {
		for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("annotations").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
		string annotationfile = "Annotations/" + tagname + "_" + fileid+ ".xml";
		if ( debug > 2 ) { cout << " - Looking for stand-off: " << annotationfile << endl; };
		// Loop through the actual items if the file exists
		if ( access( annotationfile.c_str(), F_OK ) != -1 ) { // Check whether the annotation file exists for this file
			externals[tagname]->load_file(annotationfile.c_str());
			// string xpath = "//file[@id=\""+fileid+"\"]/segment";
			string xpath = "//span";
			if ( debug > 2 ) { cout << " - Going through each item: " << xpath << endl; };
			pugi::xpath_node_set elmres = externals[tagname]->select_nodes(xpath.c_str());
			for (pugi::xpath_node_set::const_iterator it = elmres.begin(); it != elmres.end(); ++it) {
				string wlist = it->node().attribute("corresp").value();
				string toka = wlist.substr(1,wlist.find(" ")-1);
				string tokb = wlist.substr(wlist.find_last_of("#")+1);
				if ( toka == "" || tokb == "" ) { continue; };
				int posa = id_pos[toka]; // first "token" in the range
				int posb = id_pos[tokb]; // last "token" in the range
				if ( debug > 2 ) { cout << " Found a range " << tagname << " " << it->node().attribute("id").value() << " from " << toka << " (" << posa << ") to " << tokb << " (" << posb << ")" << endl; };

				write_range(posa, posb, tagname);
				for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
					formkey = formfld.attribute("key").value(); 
					if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
					formval = "";
					formval = it->node().attribute(formfld.attribute("key").value()).value();

					// write the actual data
					if ( debug > 4 ) { cout << " Writing value " << formkey << " = " << formval << " for " << tagname << ": " << posa << " to " << posb << endl; };
					write_range_value(posa, posb, tagname, formkey, formval);
				};
			};	
		};
	};
	};

};


int treatdir (string dirname) {
    struct dirent *entry;
    DIR *dp;

	if ( verbose ) {
		cout << "- Treating folder " << dirname << endl; 
	};
 
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
	cqpsettings = trainlog.first_child().append_child("settings");	
	cqpstats = trainlog.first_child().append_child("stats");	

	time_t beginT = clock(); time_t tm = time(0);
	string tmp = ctime(&tm);
	trainlog.first_child().append_attribute("starttime") = tmp.substr(0,tmp.length()-1).c_str();	

	// Read in all the command-line arguments
	for ( int i=1; i< argc; ++i ) {
		string argm = argv[i];
		
		if ( argm.substr(0,2) == "--" ) {
			int spacepos = argm.find("=");
			
			if ( spacepos == -1 ) {
				string akey = argm.substr(2);
				cqpsettings.append_attribute(akey.c_str()) = "1";
			} else {
				string akey = argm.substr(2,spacepos-2);
				string aval = argm.substr(spacepos+1);
				cqpsettings.append_attribute(akey.c_str()) = aval.c_str();
			};
		};		
	};

	// Some things we want as accessible variables
	if ( cqpsettings.attribute("debug") != NULL ) { debug = atoi(cqpsettings.attribute("debug").value()); };
	if ( cqpsettings.attribute("test") != NULL ) { test = true; verbose = true; };
	if ( cqpsettings.attribute("verbose") != NULL ) { verbose = true; };

	if ( cqpsettings.attribute("version") != NULL ) { 
		cout << "tt-cwb-encode version 1.0" << endl;
		return -1; 
	};

	// Output help information when so asked and quit
	if ( cqpsettings.attribute("help") != NULL ) { 
		cout << "Usage:  tt-cwb-encode [options]" << endl;
		cout << "" << endl;
		cout << "Reads a collection of tokenized XML files, and generates CWB binary format corpus files, which can be converted into a full CWB corpus with cwb-makeall. Settings for the conversion are typcially read from an XML style settings file, which by default is called Resources/settings.xml. More information about the structure of the settings file can be found on: http://teitok.corpuswiki.org" << endl;
		cout << "" << endl;
		cout << "Options:" << endl;
		cout << "  --debug=[n]    debug level" << endl;
		cout << "  --verbose	  verbose output" << endl;
		cout << "  --settings=[s] name of the settings file" << endl;
		cout << "  --log=[s]	  write log to file [s]" << endl;
		return -1; 
	};
	
	// Read the settings.xml file where appropriate - by default from ./Resources/settings.xml
	string settingsfile;
	string folder;
	if ( cqpsettings.attribute("settings") != NULL ) { 
		settingsfile = cqpsettings.attribute("settings").value();
	} else {
		folder = ".";
		settingsfile = "./Resources/settings.xml";
	};
    if ( xmlsettings.load_file(settingsfile.c_str())) {
    	if ( verbose ) { cout << "- Using settings from " << settingsfile << endl;   }; 	
    };


	
	pugi::xml_node parameters = xmlsettings.select_single_node("/ttsettings/cqp").node();
	if ( parameters == NULL ) {
		cout << "- No parameters for CQP found" << endl;
		return -1;
	}; 

	// Place all cqp parameter settings from the settings.xml into the cqpsettings
	for (pugi::xml_attribute_iterator it = parameters.attributes_begin(); it != parameters.attributes_end(); ++it)
	{
		if ( cqpsettings.attribute((*it).name()) == NULL ) { 
			cqpsettings.append_attribute((*it).name()) =  (*it).value();
		};
	};
	// Also take settings from the //cqp root ([item]/../..)
	for (pugi::xml_attribute_iterator it = parameters.parent().parent().attributes_begin(); it != parameters.parent().parent().attributes_end(); ++it)
	{
		if ( cqpsettings.attribute((*it).name()) == NULL ) { 
			if ( debug > 1 ) { cout << "XML Setting: "<< (*it).value() << endl; };
			cqpsettings.append_attribute((*it).name()) =  (*it).value();
		};
	};

	if ( cqpsettings.attribute("wordfld") != NULL ) { 
		wordfld = cqpsettings.attribute("wordfld").value();
	} else {
		wordfld = "form"; // By default, use @form for the word
	}
    if ( verbose ) { cout << "- Using base word form: " << wordfld << endl;   }; 	
	
	// Determine some default settings
	if ( cqpsettings.attribute("corpusfolder") != NULL ) { corpusfolder = cqpsettings.attribute("corpusfolder").value(); } 
		else { corpusfolder = "cqp"; };

	if ( cqpsettings.attribute("registryfolder") != NULL ) { registryfolder = cqpsettings.attribute("registryfolder").value(); } 
		else { registryfolder = "/usr/local/share/cwb/registry/"; };

	// Check whether the corpusfolder exists, or create it, or fail
	// TODO: Using Boost seems redundant, since TEITOK is not very Windows in any case
	if ( mkdir(corpusfolder.c_str(), S_IRWXU | S_IRWXG | S_IROTH | S_IXOTH) ) {
		if ( verbose ) { cout << "Directory Created: "<< corpusfolder << endl; };
	};

	if ( xmlsettings.select_nodes("//cqp/pattributes/item[@key=\"word\"]").empty() ) { 
		pugi::xml_node watt = xmlsettings.first_child().child("cqp").child("pattributes").append_child("item");
		watt.append_attribute("key") = "word";
	};
	if ( xmlsettings.select_nodes("//cqp/pattributes/item[@key=\"id\"]").empty() ) { 
		pugi::xml_node watt = xmlsettings.first_child().child("cqp").child("pattributes").append_child("item");
		watt.append_attribute("key") = "id";
	};
	if ( debug > 2) { xmlsettings.first_child().child("cqp").child("pattributes").print(cout); };
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

	// Write the registry file
	string corpusname;
	if ( cqpsettings.attribute("corpus") != NULL ) { corpusname = cqpsettings.attribute("corpus").value(); }
	else {
		corpusname = xmlsettings.select_single_node("//cqp/@corpus").attribute().value();
	};
	if ( corpusname == "" ) { cout << "Error: no corpus name indicated!" << endl; return -1; };
	string corpuslong;
	if ( cqpsettings.attribute("name") != NULL ) { corpuslong = cqpsettings.attribute("name").value(); }
	else {
		corpuslong = xmlsettings.select_single_node("//title/@display").attribute().value();
	};
	corpusname = strtolower(corpusname);
	string registryfile = registryfolder + corpusname;
	if ( verbose ) { cout << "Writing registry data to: " << registryfile << endl; };
	ofstream registry;
	registry.open(registryfile.c_str());
	registry << "## Registry file for the corpus " << corpusname << endl;
	registry << "## Created from XML file by TEITOK" << endl;
	registry << "## Generated by tt-cwb-encode" << endl << endl;

	registry << "NAME \"" << corpuslong << "\"" << endl;
	registry << "ID " << corpusname << endl;
	registry << "HOME " << realpath(corpusfolder.c_str(), NULL) << endl;
	registry << "INFO " << realpath(corpusfolder.c_str(), NULL) << "/.info" << endl;
	
	// Go through the pattributes
	registry << endl << "## Positional attributes on <tok>" << endl;
	string formkey; string formval; string longname;
    for ( pugi::xml_node formfld = xmlsettings.first_child().child("cqp").child("pattributes").child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
		formkey = formfld.attribute("key").value(); 
		longname = formfld.attribute("display").value();
		if ( longname == "" ) {
			string tmp = "//pattributes//item[@key=\""+formkey+"\"]/@display";
			longname = xmlsettings.select_single_node(tmp.c_str()).attribute().value();
		};
		registry << "ATTRIBUTE " << formkey << "  # " << longname << endl;
		
		if ( debug > 0 ) { cout << "Creating files for: " << formkey << endl; };
		
		// Open the files
		filename = corpusfolder+"/"+formkey+".lexicon";
		streams[formkey]["lexicon"] = new ofstream(filename.c_str());
		filename = corpusfolder+"/"+formkey+".lexicon.idx";
		files[formkey]["idx"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+"/"+formkey+".corpus";
		files[formkey]["corpus"] = fopen(filename.c_str(), "wb"); 
	};		
	
	// Throw an exception if we did not manage to create corpus.lexicon
	if ( files["word"]["corpus"] == NULL ) {
		cout << "Fatal error: failed to created files, check cqp folder exists and is writable" << endl;
		return -1;
	};
	
	// go through the sattributes on all levels
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("sattributes").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
			if ( debug > 0  ) { cout << "Creating files level: " << tagname << endl; };
		registry << endl << "## Structural attributes on <" << tagname << ">" << endl;
		registry << "STRUCTURE " << tagname << endl;
		filename = corpusfolder+"/"+tagname+".rng";
		files[tagname]["rng"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+"/"+tagname+"_xidx.rng";
		files[tagname+"_xidx"]["rng"] = fopen(filename.c_str(), "wb"); 
		for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
			formkey = formfld.attribute("key").value(); 
			if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
			longname = formfld.attribute("long").value();
			if ( longname == "" ) { longname = formfld.attribute("display").value(); };
			registry << "STRUCTURE " << tagname << "_" << formkey << "  # " << longname << endl;

			if ( debug > 0  ) { cout << "Creating attribute files for: " << formkey << endl; };
			filename = corpusfolder+"/"+tagname+"_"+formkey+".avs";
			streams[tagname+"_"+formkey]["avs"] = new ofstream(filename.c_str());
			filename = corpusfolder+"/"+tagname+"_"+formkey+".avx";
			lexidx[tagname+"_"+formkey] = 0; lexpos[tagname+"_"+formkey] = 0;
			files[tagname+"_"+formkey]["avx"] = fopen(filename.c_str(), "wb"); 
			filename = corpusfolder+"/"+tagname+"_"+formkey+".rng";
			files[tagname+"_"+formkey]["rng"] = fopen(filename.c_str(), "wb"); 
		};
	};
	// There always should be a text_id
	registry << "STRUCTURE text_id" << endl;
	if ( streams["text_id"]["avs"] == NULL ) {
		if ( debug > 4 ) { cout << "Creating text_id" << endl; };
		filename = corpusfolder+"/"+"text_id.avs";
		streams["text_id"]["avs"] = new ofstream(filename.c_str());
		filename = corpusfolder+"/"+"text_id.avx";
		lexidx["text_id"] = 0; lexpos["text_id"] = 0;
		files["text_id"]["avx"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+"/"+"xidx.rng";
		files["xidx"]["rng"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+"/"+"text_id.rng";
		files["text_id"]["rng"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+"/"+"text_id.idx";
		files["text_id"]["idx"] = fopen(filename.c_str(), "wb"); 
	};
	
	// go through the stand-off annotations
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("annotations").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
		string filename = taglevel.attribute("filename").value();
		if ( debug > 4 ) { cout << "Stand-off annotation: " << filename << endl; };

		// open the external XML file
		string fullfilename = "Annotations/"+filename;
		
		externals[tagname] = new pugi::xml_document();
		if ( 1 == 2 ) { // filename != ""
			try {
				externals[filename]->load_file(fullfilename.c_str());
			} catch(pugi::xpath_exception& e) { if ( debug > 4 ) { cout << "Failing to load: " << filename << endl; };  };
			if ( externals[filename] == NULL ) {
				cout << "Failed to load: " << fullfilename << endl; 
				taglevel.parent().remove_child(taglevel); // Remove this node since we cannot read the stand-off annotation
				continue; 
			};
			if ( verbose ) { cout << "Loading external annotations XML: " << filename << endl; };
		};
				
		registry << endl << "## Stand-off annotations of type " << tagname << endl;
		registry << "STRUCTURE " << tagname << endl;
		filename = corpusfolder+"/"+tagname+".rng";
		files[tagname]["rng"] = fopen(filename.c_str(), "wb"); 
		filename = corpusfolder+"/"+tagname+"_xidx.rng";
		files[tagname+"_xidx"]["rng"] = fopen(filename.c_str(), "wb"); 
		for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
			formkey = formfld.attribute("key").value(); 
			if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute 
			longname = formfld.attribute("long").value();
			if ( longname == "" ) { longname = formfld.attribute("display").value(); };
			registry << "STRUCTURE " << tagname + "_" + formkey << "  # " << longname << endl;

			if ( debug > 0  ) { cout << "Creating attribute files for: " << formkey << endl; };
			filename = corpusfolder+"/"+tagname+"_" + formkey+".avs";
			streams[tagname+"_" + formkey]["avs"] = new ofstream(filename.c_str());
			filename = corpusfolder+"/"+tagname+"_" + formkey+".avx";
			lexidx[tagname+"_"+formkey] = 0; lexpos[tagname+"_"+formkey] = 0;
			files[tagname+"_" + formkey]["avx"] = fopen(filename.c_str(), "wb"); 
			filename = corpusfolder+"/"+tagname+"_" + formkey+".rng";
			files[tagname+"_" + formkey]["rng"] = fopen(filename.c_str(), "wb"); 
		};

	};
		
	// This does not listen to the command line at the moment, should be reverted back to cqpsettings
	string dofolders;
	if ( cqpsettings.attribute("folder") != NULL ) { dofolders = cqpsettings.attribute("folder").value(); }
	else {
		dofolders = xmlsettings.select_single_node("//cqp/@searchfolder").attribute().value();
	};
	if ( dofolders != "" ) {
		if ( verbose ) cout << "- Indexing folder(s): " << dofolders << endl;
		char_separator<char> sep(" ");
    	tokenizer< char_separator<char> > tokens(dofolders, sep);
		BOOST_FOREACH (const string& fldr, tokens) {
			if ( debug ) {
				cout << "  - Analyzing files from: " << fldr << endl;    	
			};
			treatdir ( fldr );
		}
	} else {
		if ( verbose ) cout << "- Default training folder: ./xmlfiles" << endl;
		treatdir ( "xmlfiles" ); 
	};

	if ( verbose ) { cout << "- Calculating additional data" << endl; };


	if ( verbose ) cout << "- " << tokcnt << " tokens in CQP corpus" << endl; 
	cqpstats.append_attribute("tokens") = tokcnt;
	
	if ( cqpsettings.attribute("log") != NULL ) {
		cout << "- Saving log to: " << cqpsettings.attribute("log").value() << endl;
		trainlog.save_file(cqpsettings.attribute("log").value());
	};

}
