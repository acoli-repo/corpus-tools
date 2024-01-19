#include <iostream>
#include <sstream>
#include <stdio.h>
#include <fstream>
#include <stdlib.h>
#include <map>
#include <vector>
#include <string.h>
#include <dirent.h>
#include <sys/stat.h>
#include <arpa/inet.h>
#include "pugixml.hpp"
#include "functions.hpp"

using namespace std;

int debug = 0;
bool test = false;
bool verbose = false;

char tokxpath [50];
pugi::xml_document trainlog;
pugi::xml_node cqpsettings;
pugi::xml_node cqpstats;
vector<string> formTags;

string wordfld; // field to use for the "word" attribute
string lemmafld;
string filename;
string corpusfolder;
string registryfolder;

int tokcnt = 0;

map<string, map<string, int> > lexitems; // .lexicon ids
map<string, map<int, int> > lexcnt; // .lexicon counts (on ids)
map<string, map<string, ofstream*> > streams; // ascii output files
map<string, map<string, FILE*> > files; // real output files
map<string,int > lexidx; // max lexitems id
map<string,int > lexpos; // pos in the lexicon file
map<string,int > tokxmlpos; // tokid to xml pos

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

bool file_exists (const std::string& name) {
    ifstream f(name.c_str());
    return f.good();
}

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
	// fflush(stream); // This does not seem to be needed, and slows down the process
};

// Write a range to .rng
void write_range ( int pos1, int pos2, string tagname ) {
	if ( pos2 < pos1 ) {
		if ( debug > 0 ) { cout << "negative range for " << tagname << " = " << pos1 << " - " << pos2 << " - not writing" << endl; };
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
	if ( pos2 < pos1 ) {
		if ( debug > 0 ) { cout << "negative range for " << tagname << " / " << attname << " = " << pos1 << " - " << pos2 << " - not writing" << endl; };
		return;
	}; // We can never have negative ranges

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

	if ( debug > 3	) node.node().print(std::cout);

	const char * tokid = node.node().attribute("id").value();
	if ( tokid == NULL ) {
		if ( debug > 1 ) { cout << "Skipping - node without an ID: " << tokid << endl; };
		return;
	};

	if ( !strcmp( node.node().attribute(wordfld.c_str()).value(), "--" ) ) {
		if ( debug > 1 ) { cout << "Skipping - empty value for " << wordfld <<  ": " << tokid << endl; };
		return;
	};

	// We have a valid token - handle it
	tokcnt++;
	if ( debug > 1 ) { cout << "Token " << tokcnt << " : " << tokid  << " = " << calcform(node.node(), wordfld) << endl; };

	// Write the .lexicon, .lexicon.idx and .corpus files
	string formkey; // The key (name) for the pattribute 
	pugi::xpath_node xres; // The object corresponding to the pattribute
	string formval; // The (calculated/inherited) string for the pattribute
    for ( pugi::xml_node formfld = xmlsettings.first_child().child("cqp").child("pattributes").child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
		string xpath = formfld.attribute("xpath").value();
		formkey = formfld.attribute("key").value();
		if ( xpath != "" ) {
			if ( debug > 4 ) { cout << "Calculating XPath value for node: " << xpath << endl; };
			xres = node.node().select_node(xpath.c_str());
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

		formval = trim(replace_all(formval, "\n", " ")); // Trim white spaces

		if ( debug > 4 ) { cout << "Value: " << formkey << " = " << formval << endl; };
		if ( !lexitems[formkey][formval] ) {
			// new word
			lexitems[formkey][formval] = lexidx[formkey];
			*streams[formkey]["lexicon"] << formval << '\0'; streams[formkey]["lexicon"]->flush();
			write_network_number(lexpos[formkey], files[formkey]["idx"]);
			lexidx[formkey]++; lexpos[formkey] += formval.length() + 1;
			if ( debug > 4 ) { cout << formval << ":" << formkey << " - new value  - id " << lexidx[formkey] << " - pos " << lexpos[formkey] << endl; };
		};
		write_network_number(lexitems[formkey][formval], files[formkey]["corpus"]);
		lexcnt[formkey][lexitems[formkey][formval]]++;

	};

	// Write the word.xidx.rng
	int xmlpos1 = node.node().offset_debug()-1;
	tokxmlpos[tokid] = xmlpos1;
	std::ostringstream oss;
	node.node().print(oss);
	std::string xmltxt = oss.str();
	int xmlpos2 = xmlpos1 + xmltxt.length();
	if ( debug > 4 ) { cout << " - writing range (xidx): " << xmlpos1 << " - " << xmlpos2 << endl; };
	write_network_number(xmlpos1, files["xidx"]["rng"]);
	write_network_number(xmlpos2, files["xidx"]["rng"]);

	// write the //text/@id to text_id.idx
	write_network_number(lexidx["text_id"], files["text_id"]["idx"]);


};

void treatfile ( string filename ) {
	pugi::xml_document doc; // the current XML document being encoded

	if ( filename.find(".xml") == -1 ) {
        if ( debug > 0 ) { cout << "  Skipping non-XML file: " << filename << endl; };
		return;
	}; // We can only treat XML files and assume those always end on .xml

    // Now - read the file
	string sep = "";

	pugi::xml_parse_result docres = doc.load_file(filename.c_str(), pugi::parse_ws_pcdata);
    if ( !docres ) {
        cout << "  Failed to load XML file " << filename << endl;
        if ( verbose ) {
		    std::cout << "Error description: " << docres.description() << "\n";
        };
    	return;
    };

    string fileid = filename.substr(filename.find_last_of("/")+1,filename.find_last_of(".")-filename.find_last_of("/")-1	);

	if ( debug > 0 ) { cout << "  - Treating: " << filename << " (" << fileid << ")" << endl; };

	pugi::xpath_node resnode;

	// See if we are asked to skip this type of file
	if ( cqpsettings.attribute("restriction") != NULL
			&& doc.select_node(cqpsettings.attribute("restriction").value()) == NULL ) {
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
	map<string, int> id_pos; // Keep the corpus position for each @pos since we need those for sattributes later
	vector<pugi::xml_node> nodelist; 

	if ( cqpsettings.attribute("withemptytext") != NULL && toks.size() == 0 ) {
		// If we have no tokens in this file, but need to keep empty texts, create a single empty token inside this text
		if ( debug > 1 ) cout << "- We have no tokens in this file (" << tokxpath << ") - but we want to keep it, so let's make one" << endl;
		string textxpath = "";
		if ( cqpsettings.attribute("xpath") != NULL ) {
			textxpath = cqpsettings.attribute("xpath").value();
		} else {
			textxpath = "//text";
		};
	 
		pugi::xml_node textdoc = doc.first_child().append_child("text");
		pugi::xml_node node = textdoc.append_child("tok"); // TODO: This should use tokxpath
		node.append_attribute("id") = "w-1";
		node.append_child(pugi::node_pcdata).set_value("--");
		if ( debug > 1 ) {
			textdoc.print(cout);
			cout << endl;
		};
		toks = doc.select_nodes(tokxpath);
	};

	int pos1 = tokcnt;
	for (pugi::xpath_node_set::const_iterator it = toks.begin(); it != toks.end(); ++it)
	{
		pugi::xml_node node = it->node();

		const char * tokid = node.attribute("id").value();

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
			id_pos[tokid] = tokcnt; // Use the first <dtok> as ref for the whole <tok> for stand-off purposes
	        for ( pugi::xml_node dtoken = node.child("dtok"); dtoken != NULL; dtoken = dtoken.next_sibling("dtok") ) {
				id_pos[tokid] = tokcnt;

				treatnode(dtoken);
				nodelist.push_back(dtoken);

	    	};
		} else {
			id_pos[tokid] = tokcnt;

			treatnode(node);
			nodelist.push_back(node);

		};
	}
	int pos2 = tokcnt-1;

	string idname = filename;

	// Add the default attributes for <text>
	if ( debug > 0 ) {
		cout << "<text> " << idname << " ranging from " << pos1 << " to " << pos2 << endl;
	};

	write_range (pos1, pos2, "text" );
		if ( debug > 4 ) { cout << "  - written to text" << endl; };
	write_range_value (pos1, pos2, "text", "id", idname);
		if ( debug > 4 ) { cout << "  - written to text_id" << endl; };

	// if we have any id type fields, write to the .pos file
	pugi::xpath_node_set idflds = xmlsettings.select_nodes("//cqp/pattributes/item[@type=\"id\"]");
	for (pugi::xpath_node_set::const_iterator it1 = idflds.begin(); it1 != idflds.end(); ++it1) {
		string idfld = it1->node().attribute("key").value();
		for ( int x=0; x<nodelist.size(); x++ ) {
			pugi::xml_node node = nodelist[x];
			string refid = node.attribute(idfld.c_str()).value();
			int refpos = -1;
			if ( refid != "" ) {
				refpos = id_pos[refid];
			};
			write_network_number(refpos, files[idfld]["pos"]);
		};
	};

	// add the sattributes for all levels
	string formkey = ""; string formval = "";
	string rel_tokxpath = tokxpath;

	if ( rel_tokxpath.substr(0,1) == "/" ) {
		// Make the path relative if it is root-based
		rel_tokxpath = "." + rel_tokxpath;
	};
		if ( debug > 4 ) { cout << "  - looking for the tokens inside this range: " << rel_tokxpath << endl; };
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("sattributes").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
		string taglvl = taglevel.attribute("level").value();
		if ( taglvl.length() == 0 ) { taglvl = tagname; };
		if ( taglvl == "text" ) {
			// This is the <text> level
			if ( !(pos2+1>pos1) ) {
				// This will crash on texts without any tokens inside; do not add to CQP for now (but they should be added as indexes)
				if ( debug > 4 ) { cout << "  - Emtpy range - skipping" << endl; };
				continue;
			};
			for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
				formkey = formfld.attribute("key").value();
				if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute
				formval = ""; // empty values
				string external = formfld.attribute("external").value();
				string xpath = formfld.attribute("xpath").value();
				if ( xpath != "" ) {
					// Hard-coded XPath for range value
					pugi::xpath_node_set xres;
					pugi::xpath_node xresi;
					if ( external != "" ) {
						// XPath to be evaluated wrt "external" node
						string exfile = "";
						if ( external.find("#") == string::npos && external.substr(0,1) != "/" && external.substr(0,1) != "." ) { external = "#" + external; }; // For "incorrect" IDs
						if ( debug > 2 ) { cout << "External XML lookup: " << external << endl; };
						string tmp = doc.select_node(external.c_str()).attribute().value();
						if ( debug > 3 ) { cout << " -- lookup value: " << tmp << endl; };
						vector <string> exval;
						// Initialize srings
						if ( tmp != "" ) { 
						  	exval = split( tmp, "#" ); 
						  	exfile = exval[0];
						};
						if ( exfile != "" ) {
							exfile = "Resources/" + exfile;
							if ( exfile.substr(exfile.length()-4) == ".xml" && externals.find(exfile) == externals.end() ) {
								if ( verbose ) { cout << "Loading external XML file (for text): " << exfile << " < " << tmp << endl; };
								externals[exfile] = new pugi::xml_document();
								if ( externals[exfile]->load_file(exfile.c_str()) ) {
									// Correctly loaded external 
								} else {
									if ( verbose ) { cout << "Failed to load! " << exfile << endl; };
								};
							};
							if ( exfile.substr(exfile.length()-4) != ".xml" && debug > 0 ) {
								cout << "Invalid external lookup: " << tmp << endl;
							};
							if ( exfile != "" && externals.find(exfile) != externals.end()  ) {
								if ( exval.size() > 1 ) {
									string idlookup = "//*[@id=\""+exval[1]+"\"]";
									if ( debug > 1 ) { cout << "ID lookup: " << idlookup  << " in " << exfile << endl; };
									pugi::xml_node exnode;
									try {
										exnode = externals[exfile]->select_node(idlookup.c_str()).node();
									} catch(pugi::xpath_exception& e) { if ( debug > 4 ) { cout << "XPath error" << endl; };  };
									if ( debug > 2 && exnode ) { exnode.print(cout); };
									if ( exnode ) {
										xres = exnode.select_nodes(xpath.c_str());
									};
								} else {
									try {
										xres = externals[exfile]->first_child().select_nodes(xpath.c_str());
									} catch(pugi::xpath_exception& e) { if ( debug > 4 ) { cout << "XPath error" << endl; };  };
								};
							};
						} else if ( tmp != "" ) {
							// this is a local "external" reference
							string idlookup = "//*[@id=\""+exval[1]+"\"]";
							if ( debug > 1 ) { cout << "Local ID lookup: " << idlookup  << " in " << exfile << endl; };
							pugi::xml_node exnode;
							try {
								exnode = doc.select_node(idlookup.c_str()).node();
							} catch(pugi::xpath_exception& e) { if ( debug > 4 ) { cout << "XPath error" << endl; };  };
							if ( debug > 2 && exnode ) { exnode.print(cout); };
							if ( exnode ) {
								xres = exnode.select_nodes(xpath.c_str());
							};
						};
					} else {
						 xres = doc.select_nodes(xpath.c_str());
					};
					formval = ""; string valsep = "";
					// Loop through the values - break after the first unless defined as values="multi"
					for (pugi::xpath_node_set::const_iterator it = xres.begin(); it != xres.end(); ++it) {
						xresi = *it; string formival = "";
						if ( xresi.attribute() ) {
							formival = xresi.attribute().value();
						} else if ( formfld.attribute("xml") ) {
							// take the XML content as value
							string xmltype = formfld.attribute("xml").value();
							std::ostringstream oss;
							xresi.node().print(oss);
							if ( xmltype == "raw" ) {
								formival += oss.str();
							} else {	
								// Flatten the content
								formival = oss.str();
								formival = preg_replace(formival, "<[^>]+>", "");
								if ( xmltype == "normalize" ) {
									formival = preg_replace(formival, "\\s+", " ");
									formival = preg_replace(formival, "^\\s+", "");
									formival = preg_replace(formival, "\\s+$", "");
								};
							};
						} else {
							formival = xresi.node().child_value();
						};
						formval += valsep + formival;
						string valtype = formfld.attribute("values").value();
						if ( valtype != "multi" ) { break; };
						valsep = formfld.attribute("multisep").value();
						if ( valsep.empty() ) valsep = ",";
					};
				};

				// Make sure to remove all linebreaks from the value to avoid problems later, say in the cwb-decode VRT output
				formval = trim(replace_all(formval, "\n", " ")); // Trim white spaces

				write_range_value (pos1, pos2, "text", formkey, formval);
			}; 
		} else if ( taglvl != "" ) {
			// Add non-text level attributes (skip empty elements)
			string xpath = "//text//" + taglvl;
			if ( debug > 2 ) { cout << "Looking for " << taglvl  << " = " << xpath << endl; };
			string toklistatt = "sameAs";
			if ( taglevel.attribute("toklist") != NULL ) { toklistatt = taglevel.attribute("toklist").value(); }
			string tmpxpath;
			if ( taglvl == "tok[dtok]" ) {
				tmpxpath = "dtok";
			} else {
				tmpxpath = rel_tokxpath;
			}
			if ( debug > 4 ) { cout << " - Relative xpath: " << tmpxpath << endl; };
			if ( debug > 4 ) { cout << " - Toklist attribute (for empty nodes): " << toklistatt << endl; };
			
			// Loop through the actual items
			pugi::xpath_node_set elmres = doc.select_nodes(xpath.c_str());
			for (pugi::xpath_node_set::const_iterator it = elmres.begin(); it != elmres.end(); ++it) {

				pugi::xpath_node_set rel_toks = it->node().select_nodes(tmpxpath.c_str());
				
				// Determine the XXX_xidx.rng
				int xmlpos1 = it->node().offset_debug()-1;
				string nextxp = "./following::" + taglvl;
				int xmlpos2 = it->node().select_node(nextxp.c_str()).node().offset_debug()-1;
				if ( xmlpos2 < 0 ) {
					// last result - calculate the end of the XML
   					pugi::xml_node mtxt = doc.child("TEI").child("text");
					std::ostringstream oss;
					mtxt.print(oss, "", pugi::format_raw);
					std::string raw_xml = oss.str();
					xmlpos2 = mtxt.offset_debug() + raw_xml.size();
				};
				
				string toka; string tokb;
				string wlist = "";
				if ( it->node().attribute(toklistatt.c_str()) ) {
					wlist = it->node().attribute(toklistatt.c_str()).value();
				};
				if ( wlist != "" ) {
					// For empty node that have a @sameAs="#w-3 #w-7" type of content
					string wlist = it->node().attribute(toklistatt.c_str()).value();
					toka = wlist.substr(1,wlist.find(" ")-1);
					tokb = wlist.substr(wlist.find_last_of("#")+1);
					if ( debug > 4 ) { 
						cout << " Explicit token list: " << toka << " - " << tokb << endl;
					};
				} else if ( rel_toks.empty() ) {
					if ( toklistatt == "implicit" || taglvl == "pb" || taglvl == "lb" ) {
						// TODO: for empty nodes like <pb/> - go from the first token after to the first token before the next....
						pugi::xpath_node tmp = it->node().select_node("./following::tok");
						if ( tmp ) {
							toka = tmp.node().attribute("id").value();
						};
						pugi::xpath_node tmpb = it->node().select_node(nextxp.c_str());
						if ( tmpb ) {
							tmp = tmpb.node().select_node("./preceding::tok[1]");
							if ( tmp ) {
								tokb = tmp.node().attribute("id").value();
							};
						} else {
							// This has to be the last element - go until the end
							pugi::xpath_node_set::const_iterator tmpit = toks.end(); --tmpit;
							tokb = tmpit->node().attribute("id").value();
							cout << " So found as the very last tok: " << tokb << endl; 
						};
						if ( debug > 4 ) { 
							cout << " Implicit token list: " << toka << " - " << tokb << endl;
						};
					} else {
						continue;
					};
				} else {
					// Standard tokens below the node
					xmlpos2 = it->node().select_node("./following::*").node().offset_debug()-1; // For closed regions, the region end before any next node
					toka = rel_toks[0].node().attribute("id").value();
					tokb = rel_toks[rel_toks.size()-1].node().attribute("id").value();
					if ( debug > 4 ) { 
						cout << " Dependent token list: " << toka << " - " << tokb << endl;
					};
				};

				// Skip this is we do not have any tokens inside the range (or one of them does not have an ID?)
				if ( id_pos.find(toka) == id_pos.end() || id_pos.find(tokb) == id_pos.end() ) {
					if ( debug > 2 ) { cout << " Empty range " << tagname << " " << it->node().attribute("id").value() << " from " << toka << " to " << tokb << endl; };
					continue;
				};

				int posa = id_pos[toka]; // first "token" in the range
				int posb = id_pos[tokb]; // last "token" in the range

				if ( debug > 2 ) { cout << " Found a range " << tagname << " " << it->node().attribute("id").value() << " from " << toka << " (" << posa << ") to " << tokb << " (" << posb << ")" << endl; };

				write_range(posa, posb, tagname ); 

				// Write the XXX_xidx.rng
				if ( debug > 4 ) { cout << "Writing XIDX for " << tagname << " = " << xmlpos1 << " - " << xmlpos2 << endl; };
				write_network_number(xmlpos1, files[tagname + "_xidx"]["rng"]);
				write_network_number(xmlpos2, files[tagname + "_xidx"]["rng"]);

				for ( pugi::xml_node formfld = taglevel.child("item"); formfld != NULL; formfld = formfld.next_sibling("item") ) {
					formkey = formfld.attribute("key").value();
					if ( formkey == "" ) { continue; }; // This is a grouping label not an sattribute
					formval = "";
					xpath = formfld.attribute("xpath").value();
					if ( debug > 4 ) { cout << " - Looking for: " << formkey << " = " << xpath << endl; };
					if ( xpath != "" ) {
						pugi::xpath_node xres;
						string external = formfld.attribute("external").value();
						string extid = ""; pugi::xpath_node extval;
						if ( external != "" ) {
							// TODO: this is only external to the NODE, not a lookup from an external XML file
							// so with an "external", we should split xpath on tmp = # and lookup tmp[1] potentially in Resources/tmp[0]
							extval = it->node().select_node(external.c_str());
							if ( extval != NULL && extval.attribute() != NULL && extval.attribute().value() != NULL ) { extid = extval.attribute().value(); };
						};
						if ( external == "" ) {
							if ( debug > 2 ) { cout << "Internal lookup: " << xpath << endl; };
							xres = it->node().select_node(xpath.c_str());
							if ( xres != NULL ) {
								formval = pugi::xpath_query(".").evaluate_string(xres);;
							} else {
								formval = "";
							};
						} else if ( extval == NULL || extid == "" || extid.back() == '#' ) {
							if ( debug > 2 ) { cout << "No node or value found for " << external << endl; };
							formval = "";
						} else {
							if ( debug > 4 ) { cout << "External lookup start: " << external << " + " << extid << " + " << xpath << endl; };
							if ( extid.find("#") == string::npos ) { extid = "#" + extid; }; // For "incorrect" IDs
							vector<string> vtmp = split(extid, "#");
							string exfile = "";
							if ( vtmp.size() == 1 ) {
								extid = vtmp[0];
							} else {
								exfile = vtmp[0];
								extid = vtmp[1];
							};
							// Protect for XPath
							extid = replace_all(extid, "&", "&amp;" );
							extid = replace_all(extid, ">", "&gt;" );
							extid = replace_all(extid, "<", "&lt;" );
							extid = replace_all(extid, "\n", " " );
							extid = replace_all(extid, "'", "&quot;" ); // There is no quote escaping in XPath literals
			
							if ( exfile != "" ) {
								exfile = "Resources/" + exfile;
								if ( exfile.length() > 4 && exfile.substr(exfile.length()-4) == ".xml" && externals.find(exfile) == externals.end()  ) {
									if ( verbose ) { cout << "Loading external XML file (for sattributes): " << exfile << " < " << vtmp[1] << endl; };
									externals[exfile] = new pugi::xml_document();
									if ( externals[exfile]->load_file(exfile.c_str()) ) {
										// Correctly loaded external 
									} else {
										if ( verbose ) { cout << "Failed to load! " << exfile << endl; };
									};
								};
							};
							pugi::xpath_node xext;
							string extxpath = "//*[@id='"+extid+"' or @xml:id='"+extid+"']";
							if ( exfile != "" && externals.find(exfile) != externals.end()  ) {
								if ( debug > 4 ) { cout << " - Compiling external lookup: " << external << " = " << exfile << " / " << extid << " = " << extxpath << endl; };
								xext = externals[exfile]->select_node(extxpath.c_str());
							} else {
								if ( debug > 4 ) { cout << " - Compiling internal lookup: " << external << " = (local) "  << " / " << extid << " = " << extxpath << endl; };
								xext = doc.select_node(extxpath.c_str());
							};
							if ( xext ) {
								if ( debug > 4 ) { xext.node().print(cout); };
								xres = xext.node().select_node(xpath.c_str());
								formval = pugi::xpath_query(".").evaluate_string(xres);;
								if ( debug > 3 ) { cout << " - External lookup: " << external << " = " << extxpath << " / " << exfile << " / " << xpath << " => " << xext.node() << " : " << formval << endl; };
							} else if ( debug ) {
								 cout << " - External lookup failed: " << extxpath << endl;
							};
						};
					

					} else if ( !strcmp(formfld.attribute("type").value(), "form") ) {
						// calculate the form for form-type tags (on mtok and tok[dtok])
						formval = calcform(it->node(), formkey);
						if ( debug > 3 ) { cout << " -- calculating form for " << tagname << " - " << formkey << " = " << formval << endl; };
					} else {
						formval = it->node().attribute(formfld.attribute("key").value()).value();
					};


					// Make sure to remove all linebreaks from the value to avoid problems later, say in the cwb-decode VRT output
					formval = trim(replace_all(formval, "\n", " ")); // Trim white spaces

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
		if ( file_exists( annotationfile ) ) { // Check whether the annotation file exists for this file
			externals[tagname]->load_file(annotationfile.c_str());
			// string xpath = "//file[@id=\""+fileid+"\"]/segment";
			string xpath = "//span";
			if ( debug > 2 ) { cout << " - Going through each item: " << xpath << endl; };
			pugi::xpath_node_set elmres = externals[tagname]->select_nodes(xpath.c_str());
			for (pugi::xpath_node_set::const_iterator it = elmres.begin(); it != elmres.end(); ++it) {
				string wlist = it->node().attribute("corresp").value();
				string toka = wlist.substr(1,wlist.find(" ")-1);
				string tokb = wlist.substr(wlist.find_last_of("#")+1);
				if ( toka == "" || tokb == "" ) { 
					if ( verbose ) { cout << " Incorrect range: " << tagname << " " << it->node().attribute("id").value() << " from " << toka << " to " << tokb << endl; };
					continue; 
				};
				int posa = id_pos[toka]; // first "token" in the range
				int posb = id_pos[tokb]; // last "token" in the range
				if ( posb < posa ) { 
					if ( verbose ) { cout << " Incorrect range: " << tagname << " " << it->node().attribute("id").value() << " from " << toka << " (" << posa << ") to " << tokb << " (" << posb << ")" << endl; };
					continue;
				}
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

	// For debugging, write out variable sizes
	if ( debug > 1 ) {
		// TODO: This does not work
		// cout << "Memory used in lexitems: " << sizeof(lexitems) + lexitems.size() * (sizeof(decltype(lexitems)::key_type) + sizeof(decltype(lexitems)::mapped_type)) << endl;
	};
	
	doc.reset(); // clear the variable to prevent potential memory leakage
	tokxmlpos.clear();

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


	// If there is an index.txt in the folder, read only the files specified there
    struct stat st;
	ifstream idxfile;
	idxfile.open(dirname + "/index.txt");
    if ( idxfile ) {
    
    	if ( debug ) {
	    	cout << "Reading file list from index.txt" << endl;
    	};
    	
    	string line;
		while( std::getline( idxfile, line ) ) {
			string ffname = dirname + '/' + line;
		    treatfile(ffname);
		}
    	
	} else {
		dp = opendir(path);
		if (dp == NULL) {
			if ( debug ) {
				cout << "  Failed to open dir: " << path << endl;
			};
			return -1;
		}

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
	};
	
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
	if ( cqpsettings.attribute("test") != NULL ) { test = true; verbose = true; };
	if ( cqpsettings.attribute("verbose") != NULL ) { verbose = true; };
	if ( cqpsettings.attribute("debug") != NULL ) { debug = atoi(cqpsettings.attribute("debug").value()); verbose = true; };

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

	// Check the definition for the CQP corpus in the settings
	pugi::xml_node parameters = xmlsettings.select_node("/ttsettings/cqp").node();
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
		else { registryfolder = "cqp"; };
		// else { registryfolder = "/usr/local/share/cwb/registry/"; };

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
	pattlist = xmlsettings.select_nodes("//xmlfile//pattributes//item | //xmlfile//sattributes//item");
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
		corpusname = xmlsettings.select_node("//cqp/@corpus").attribute().value();
	};
	if ( corpusname == "" ) { cout << "Error: no corpus name indicated!" << endl; return -1; };
	string corpuslong;
	if ( cqpsettings.attribute("name") != NULL ) { corpuslong = cqpsettings.attribute("name").value(); }
	else {
		corpuslong = xmlsettings.select_node("//title/@display").attribute().value();
	};
	corpusname = strtolower(corpusname);
	string registryfile = registryfolder + '/' + corpusname;
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
			longname = xmlsettings.select_node(tmp.c_str()).attribute().value();
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

		string tmp = formfld.attribute("type").value();
		if ( tmp == "id" ) { // For id (ref) fields, also open a .pos file
			filename = corpusfolder+"/"+formkey+".corpus.pos";
			files[formkey]["pos"] = fopen(filename.c_str(), "wb");
		};

	};

	// Throw an exception if we did not manage to create corpus.lexicon
	if ( files["word"]["corpus"] == NULL ) {
		cout << "Fatal error: failed to create CQP files (no cqp/word.lexicon), check cqp folder exists and is writable" << endl;
		return -1;
	};

	// go through the sattributes on all levels
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("sattributes").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
		if ( tagname == "" ) { continue; };
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
		filename = corpusfolder+"/"+"text_id.rng";
		files["text_id"]["rng"] = fopen(filename.c_str(), "wb");
		filename = corpusfolder+"/"+"text_id.idx";
		files["text_id"]["idx"] = fopen(filename.c_str(), "wb");
	};
	// We always make a text-level rng file
	filename = corpusfolder+"/"+"xidx.rng";
	files["xidx"]["rng"] = fopen(filename.c_str(), "wb");

	// go through the stand-off annotations
	for ( pugi::xml_node taglevel = xmlsettings.first_child().child("cqp").child("annotations").child("item"); taglevel != NULL; taglevel = taglevel.next_sibling("item") ) {
		string tagname = taglevel.attribute("key").value();
		string filename = taglevel.attribute("filename").value();
		if ( debug > 4 ) { cout << "Stand-off annotation: " << filename << endl; };

		// open the external XML file
		string fullfilename = "Annotations/"+filename;

		// TODO: This should be made to work 
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
		dofolders = xmlsettings.select_node("//cqp/@searchfolder").attribute().value();
	};
	if ( dofolders != "" ) {
		if ( verbose ) cout << "- Indexing folder(s): " << dofolders << endl;
		vector<string> tokens = split(dofolders, ",");
		for( vector<string>::iterator it2 = tokens.begin(); it2 != tokens.end(); it2++ ) {
			string fldr = *it2;
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

	// TODO: Write the .cnt files and such directly here to drop dependency on CWB altogether

	if ( verbose ) cout << "- " << tokcnt << " tokens in CQP corpus" << endl;
	cqpstats.append_attribute("tokens") = tokcnt;

	if ( cqpsettings.attribute("log") != NULL ) {
		cout << "- Saving log to: " << cqpsettings.attribute("log").value() << endl;
		trainlog.save_file(cqpsettings.attribute("log").value());
	};

}
