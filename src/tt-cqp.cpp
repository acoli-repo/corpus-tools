// TT-CQP - a custom version of CQP (from the Corpus Workbench) to address some issues in TEITOK
// (c) Maarten Janssen 2018

#include <iostream>
#include <sstream>  
#include <stdio.h>
#include <stdlib.h>
#include <fstream>
#include <map>
#include <vector>
#include <dirent.h>
#include <sys/stat.h>
#include <arpa/inet.h>
#include "pugixml.hpp"
#include "functions.hpp"
#include <math.h>       /* pow */
#include <algorithm>

using namespace std;

// TODO: Wish list
// within
// wildcard tokens (probably needs a restructuring of the match strategy)
// cut
// subset (or restrict)
// intersection, join, difference
// discard
// --skipbase=nform -> [word="from"] [word="here"] == [word="from"] [nform=""]* [word="here"]
// --mwe=contr -> [word="del"]  == ([word="del"]|<contr nform="del">[]+</contr>)
// match.s contains b:[word="here"]
// mean text frequency
// tiger search
// make word=".*" (and []) catch all ranges rather than do a real regex
// stats: mean text frequency

// Forward declarations
string pos2str(string a, int b);
vector<int> idx2pos(string a, int b);
int idx2cnt(string a, int b);
int str2idx(string a, string b);
int str2cnt ( string a, string b );
vector<int> regex2idx ( string a, string b, string c );
vector<int> regex2ridx (string a, string b );
string rng2xml( int a, int b );
int pos2relpos ( string attname, int pos );
string ridx2str ( string attname, int idx );
vector<int> pos2ridx ( string attname, int pos );
vector<int> ridx2rng ( string a, int b );
map<string,FILE*> files;
int ridx2cnt ( string attname, int ridx );

// For temporary debugging, define a special stdout
ostream& dbout = cout;

class cqlresult;
class cqlfld;

map<string, cqlresult> subcorpora;

int debug = 0;
bool test = false;
bool verbose = false;
bool prompt = false;
string output; // type of output (csv, json, xml)
string mwe; // what, if anything, to treat as MWE region
string cqpfolder;
string corpusname;
string regfolder;
int kleene; // hard boundary on kleene star
int corpussize;
string last;

// Define some XML documents
pugi::xml_document logfile;
pugi::xml_node settings;
pugi::xml_node results;
pugi::xml_document xmlsettings;

bool resmatch ( string a, string b, string matchtype = "=", string flags = "" ) {
	// Check whether two strings "match" using various conditions
	
	// TODO: > < !> !< 
	// Maybe: >deps or >>
	
	if ( std::size_t found = flags.find("c") != std::string::npos ) { 
		a = str2lower(a); b = str2lower(b);
	}; 
	
	if ( matchtype == "=" ) { // Regex ==
		return preg_match(a, b);
	} else if ( matchtype == "==" ) { // String == 
		return a == b;
	} else if ( matchtype == "!=" ) {
		if ( a == "" ) return false; // With the default regex non-match, we exclude empty strings
		return !preg_match(a, b);
	} else if ( matchtype == ">" ) {
		return intval(a) > intval(b);
	} else if ( matchtype == "<" ) {
		return intval(a) < intval(b);
	} else if ( matchtype == "!==" ) {
		return a != b;
	}
	return false;
};



class cqlmatch {
	// Holds a match to a CQL query
	public:
	map<string,int> named; // The named parts, including "match", "matchend"
	int ind; // The index of the token in the CQL query
	int min; int max; // Minimum and maximum values for positions - typically coming from the within command
	map<int, vector<int> > options; // Non-resolved tokens hold an array of possible positions
};

bool file_exists (const std::string& name) {
    ifstream f(name.c_str());
    return f.good();
}

bool streamopen ( FILE **stream, string filename, bool throwerror = true ) {	
	FILE* file;
	if ( !files[filename] ) { // TODO: this keeps trying when failing to open
		file = fopen(filename.c_str(), "rb"); 
		files[filename] = file;
 		if ( file == NULL && throwerror ) { cout << "Failed to open: " << filename << endl; }; // TODO: throw an exception the first time?
	};
	*stream = files[filename];

 	if ( *stream == NULL ) {
 		return false;
 	};
	
	return true;
};

class cqlfld {
	// Parse a CQL field using in tabulate, sort, etc. - say match.word, matchend[2].text_id, etc.
	// additional options over CQL: substr(fld, start, end)
 
	public:
	string rawfld; string fld;
	pugi::xml_node flddef;
	string fldname;
	string rawbase;
	string fldatt;
	map<int,string> base; // what to use as base(s) (match, matchend, named, idfield)
	map<int,int> offset; // where it is wrt base
	map<int,int> sub; // For substring matches
	map<string,int> named; int keyword;
		
	void setfld ( string flditem, map<string,int> trg ) {
		// Initialize the field
	
		vector<string> m;
		rawfld = flditem; 
		named = trg;

		string tmp1; string tmp2;		
		// substr(match)
		
		// match.fld
		if ( preg_match (flditem, "([^ ]+)\\.([^ ]+)", &m ) ) {
			string posind = m[1]; string value;
			fld = m[2]; rawbase = posind;

        	vector<string> parts = split( posind, ".." ); // we just have to skip 2 to get ..
        	for (int ii=0; ii<parts.size(); ii++ ) {
        		int i = ii; string dopart = parts[ii]; vector<string> ms;
				if ( preg_match (dopart, "(.*)\\[(-?\\d+)\\]",  &ms ) ) {
					dopart = ms[1]; offset[i] = intval(ms[2]);
				} else offset[i] = 0;
				
				if ( dopart == "match") { 
					base[i] = "match"; 
				} else if ( dopart == "matchend") { 
					base[i] = "matchend"; 
				} else if ( named[dopart] ) { // named tokens (including target and keyword)
					base[i] = dopart; 
				} else if ( file_exists(cqpfolder + dopart + ".corpus.pos" ) ) { // with type=id fields using .pos
					base[i] = dopart;  
				};		

			};	
		};
		
		if ( preg_match (fld, "substr\\((.*?),(\\d+),(\\d+)\\)", &m ) ) {
			fld = m[1]; tmp1 = m[2]; tmp2 = m[3];
			sub[0] = intval(tmp1);  sub[1] = intval(tmp2);
		};

		// lookup the field definition and display name in settings.xml (when available)
		if ( preg_match (fld, "([^ ]+)_([^ ]+)", &m ) ) {
			string tmp1 = m[1]; string tmp2 = m[2];
			string xpath = "//sattributes//item[@key='"+tmp1+"']//item[@key='"+tmp2+"']";
			flddef = xmlsettings.select_single_node(xpath.c_str()).node();
			xpath = "//sattributes//item[@key='"+tmp1+"']//item[@key='"+tmp2+"' and @display]"; // run xpath again to also use xmlsettings when applicable
			fldname = xmlsettings.select_single_node(xpath.c_str()).node().attribute("display").value();
		} else {
			string xpath = "//pattributes//item[@key='"+fld+"']";
			flddef = xmlsettings.select_single_node(xpath.c_str()).node();
			xpath = "//pattributes//item[@key='"+fld+"' and @display]"; // run xpath again to also use xmlsettings when applicable
			fldname = xmlsettings.select_single_node(xpath.c_str()).node().attribute("display").value();
		};
		if ( fldname == "" ) fldname = fld;

	
		fldatt = fld.substr(fld.find("_")+1);
								
	};
	
	string value(cqlmatch match, string matchype = "=" ) { // non-string results should just get casted back later
		// Return the result on the field for a match in the result vector
		
		vector<string> m; string value;
		
		int posstart; int pos;  int posend; 
		if ( base[0] == "match" || base[0] == "" ) {
			pos = match.named["match"];
		} else if ( base[0] == "matchend" ) {
			pos = match.named["matchend"];
		} else if ( named[base[0]] ) { // named tokens (including target and keyword)
			pos = match.named[base[0]];
		} else {
			string relbase = "match"; // relpos of match or target when specified
			if ( named["target"] ) { relbase = "target"; };
			pos = pos2relpos(base[0], match.named[relbase]); 
		};
		posstart = pos + offset[0];
		
		if ( base[1] != "" ) {
			if ( base[1] == "match"  ) {
				pos = match.named["match"];
			} else if ( base[1] == "matchend" ) {
				pos = match.named["matchend"];
			} else if ( named[base[1]] ) { // named tokens (including target and keyword)
				pos = match.named[base[0]];
			} else if ( base[1] != "" ) {
				string relbase = "match"; // relpos of match or target when specified
				if ( named["target"] ) { relbase = "target"; };
				pos = pos2relpos(base[1], match.named[relbase]); 
			};
			posend = pos + offset[1];
		} else posend = posstart;
	
		// sattribute or pattribute
		value = ""; string sep = "";
		for ( int i= posstart; i<=posend; i++ ) {
			value += sep;
			if ( preg_match (fld, "(.*)_(.*)", &m ) ) {
				vector<int> ridx = pos2ridx(fld, i);
				string list; string lsep = "";
				for (int j=0; j<ridx.size(); j++ ) {
					value += lsep + ridx2str(fld, ridx[j]); 
					lsep = ",";
				};
				value += list;
			} else {
				value += pos2str(fld, i); 
			};
		
			if ( sub[1] > 0 ) {
				value += value.substr(sub[0], sub[1]);
			};
			sep = " ";
		};

		return value;
	};
	
};

string valstring (cqlfld fld, string val ) {
	// See if we need to provide kselect, select, or other translation options
	string valstring = val;

	string fldtype = fld.flddef.attribute("type").value();

	if ( fldtype  == "kselect" ) { valstring = "{%" + fld.fldatt + "-" + val + "}"; };

	return valstring;
};

// Sorting class for matches, so that we can pass arguments to the sort function (ie sortfield)
bool compareMatches(const cqlmatch t1, const cqlmatch t2, cqlfld sortfld ){
	return sortfld.value(t1) < sortfld.value(t2);
};
class Matchsorter {	
    cqlfld sortfld_;
	public:
		Matchsorter(cqlfld sortfld){ 
			sortfld_ = sortfld; 
		}
		bool operator()(cqlmatch t1, cqlmatch t2) const {
			return compareMatches( t1 , t2 , sortfld_ );
		}
};

class cqltok {
	// Holds a token-based restriction on a match (word="A.*")
	
	public:
	string rawdef;
	int partnr;
	int idx;
	string wildcard;
	string flags;
	string partname;
	
	string left; string matchtype;string right;
	string leftstring; string rightstring;
	cqlfld leftfield; cqlfld rightfield;

	int rank;

	bool done;
};

void checkcond ( cqltok ctok, vector<cqlmatch> *match  ) {
	// Check a cqltok on the match list
	
	if ( ctok.done ) {
		return;
	};
	
	string part = ctok.rawdef;
	string wildcard = ctok.wildcard;
	string partname = ctok.partname;
	string flags = ctok.flags;
	int partnr = ctok.partnr;
				
	// Attribute matching string or regex
	string left = ctok.left; left = trim(left);
	string matchtype = ctok.matchtype;
	string right = ctok.right; right = trim(right);
	
	string leftval; string rightval;
	
	// TODO: This should allow wildcards						
	vector<cqlmatch> tocheck = *match;
	match->clear(); int ofs; vector<int> ops;
	for( vector<cqlmatch>::iterator it2 = tocheck.begin(); it2 != tocheck.end(); it2++ ) {
		
		vector<int> options = it2->options[partnr];
		if ( options.size() == 0 ) {
			// New token - initialize the options
			if ( it2->options[partnr-1].size() > 0 ) {
				ops = it2->options[partnr-1];
				ofs = +1;
			} else if ( it2->options[partnr+1].size() > 0 ) {
				ops = it2->options[partnr+1];
				ofs = -1;
			} else {
				return; // nothing to hook on to
			};
			for ( int j=0; j<ops.size(); j++ ) {
				if ( ops[j] == -1 ) { 
					vector<int> oldopts = it2->options[partnr-2*ofs];
					for ( int k=0; k<oldopts.size(); k++ ) {
						if ( oldopts[k] == -1 ) { continue; };
						options.push_back(oldopts[k] + ofs); 	
 					};
 					continue; 
				};
				int max;
				if ( wildcard == "+" || wildcard == "*" || wildcard == "+?" || wildcard == "*?" ) {
					max = kleene; // restrict to the max kleene star settings
				} else {
					max = 1;
				};
				for ( int k=0; k<max; k++ ) {
					int newidx = ops[j] + ofs + ofs*k;
					// TODO: skip over "empty" tokens
					options.push_back(newidx); // TODO: use unique at some point	
				};
				if ( wildcard == "?" || wildcard == "*" ) options.push_back(-1);
			};
		};
		
		it2->options[partnr].clear(); map<int,bool> done;
		for ( int k=0; k<options.size(); k++ ) {
			int focus = options[k]; 
			if ( done[focus] ) continue; // Skip duplicates
			
			if ( focus == -1 ) {
				it2->options[partnr].push_back(focus);
				continue;
			};
		
			// Calculate the value for the left-hand side
			if ( ctok.leftfield.rawbase != "" ) {
				// an cqlfld
				leftval = ctok.leftfield.value(*it2);
			} else if ( ctok.leftstring != "" ) {
				// a (regex) string
				leftval = ctok.leftstring;
			} else {
				// an attribute
				leftval = pos2str(left, focus);
			};
		
			// Calculate the value for the right-hand side
			if ( ctok.rightfield.rawbase != "" ) {
				rightval = ctok.rightfield.value(*it2);
			} else if ( ctok.rightstring != "" ) {
				// a (regex) string
				rightval = ctok.rightstring;
			} else {
				// an attribute
				rightval = pos2str(right, focus);
			};
		
			// Keep value if the two sides match (using the matchype)
			if ( resmatch(leftval, rightval, matchtype, flags) ) { 
				it2->options[partnr].push_back(focus);
			};
		};	
		if ( it2->options[partnr].size() > 0 ) match->push_back(*it2);
	
	};
	if ( debug ) { cout << "Size after " << left << matchtype << right << ":" << match->size() << endl; };

};

void sqlparse (string sql) {
	// We can use a light SQL version to search ranges (mostly meant for text attributes)
	vector<string> m; vector<int> match; vector<string> parts; vector<string> attlist;
	
	if ( preg_match (sql, "select (.*) from (.*) where (.*)", &m ) ) { // TODO: , std::regex_constants::icase
		string atts = m[1]; string rangetype = m[2]; string conds = m[3];

		if ( atts != "" ) attlist = split( atts,  "," );

		if ( conds == "" ) {
			
		} else {
			parts = split( conds, "&" );
			for ( int k=0; k<parts.size(); k++ ) {
				string cond = parts[k]; string word; cond = trim(cond);
				if ( cond == "" ) continue;
				if ( debug ) cout << "Checking " << cond << endl;

				string left; string right; string matchtype;
				if ( preg_match (cond, "(.*)(!?[=<>])(.*)", &m ) ) {
					left = m[1]; left = trim(left);
					matchtype = m[2];
					right = m[3]; right = trim(right);
				};

				if ( k == 0 ) {
			
					// Initialize the list of matches
					string attname = rangetype + "_" + left; 
					if ( preg_match (right, "\"(.*)\"", &m ) ) { // TODO: Do more than just match
						word = m[1];
						match = regex2ridx(attname, word);
					};
				
				} else {
				
					vector<int> tocheck = match;
					match.clear();
					for ( int j=0; j<tocheck.size(); j++ ) {
						int ridx = tocheck[j]; 
						string attname = rangetype + "_" + left; 
						if ( preg_match (right, "\"(.*)\"", &m ) ) {
							word = m[1];
							if ( resmatch(ridx2str(attname, ridx), word) ) {
								match.push_back(ridx);
							};
						};
					};
				};

			};
		};
		
		// Now, tabulate this
		for ( int k=0; k<match.size(); k++ ) {
			int ridx = match[k]; string sep;
			for (int j=0; j<attlist.size(); j++ ) {
				string attname = attlist[j]; attname = trim(attname);
				attname = rangetype + "_" + attname;
				cout << sep << ridx2str(attname, ridx);
				sep = "\t";
			};
			cout << endl;
		};
	
	} else if ( preg_match (sql, "select (.*) from (.*)", &m ) ) { // TODO: , std::regex_constants::icase
	
		string atts = m[1]; string rangetype = m[2]; 

		if ( atts == "*" ) {
			DIR *dir;
			struct dirent *ent;
			if ((dir = opendir (cqpfolder.c_str())) != NULL) {
			  /* print all the files and directories within directory */
			  while ((ent = readdir (dir)) != NULL) {
				string fname = ent->d_name;
				if ( preg_match(fname, rangetype+"_(.*).avs", &m) ) attlist.push_back(m[1]);
			  }
			  closedir (dir);
			} else {
			  /* could not open directory */
			  perror ("");
			  cout << "Failed to read CQP folder " << cqpfolder << endl;
			}
		} else if ( atts != "" ) attlist = split( atts,  "," );

		// Initialize the list of matches (all ranges)
		string filename = cqpfolder + rangetype + ".rng";
		FILE* stream; 
		if ( !streamopen(&stream, filename) ) return;
		fseek(stream, 0, SEEK_END); int max = ftell(stream)/8; 
		for ( int i=0; i< max; i++ ) {
			match.push_back(i);
		};
		
		// Now, tabulate this
		for ( int k=0; k<match.size(); k++ ) {
			int ridx = match[k]; string sep;
			for (int j=0; j<attlist.size(); j++ ) {
				string attname = attlist[j]; attname = trim(attname);
				attname = rangetype + "_" + attname;
				cout << sep << ridx2str(attname, ridx);
				sep = "\t";
			};
			cout << endl;
		};

	} else if ( preg_match (sql, "use (.*)", &m ) ) { // TODO: , std::regex_constants::icase
		corpusname = m[1];
		settings.attribute("corpusname").set_value(corpusname.c_str());
		str2lower(corpusname); vector<string> m;
		string filename = regfolder + corpusname;
		ifstream myfile( filename.c_str() ); string line;
		if (myfile) {
			while ( getline( myfile, line ) )  {
				if ( preg_match(line, "HOME (.*)", &m ) ) {
					cqpfolder = m[1];
				};
			}
			myfile.close();
		} else {
			cout << "Failed to read corpus definition: " << filename << endl;
		}
		int lastch = cqpfolder.length() - 1; 
		if ( cqpfolder[lastch] != '/' ) { cqpfolder += "/"; };
		cout << "New corpus folder: " << cqpfolder << endl;
	} else if ( sql == "show corpora" || sql == "show databases"  ) {
		DIR *dir;
		struct dirent *ent;
		if ((dir = opendir (regfolder.c_str())) != NULL) {
		  /* print all the files and directories within directory */
		  while ((ent = readdir (dir)) != NULL) {
		  	string fname = ent->d_name;
			if ( fname.substr(0,1) != "." ) cout << fname << endl;
		  }
		  closedir (dir);
		} else {
		  /* could not open directory */
		  perror ("");
		  cout << "Failed to read registry folder" << endl;
		}
	} else if ( preg_match (sql, "show tables", &m ) ) { // TODO: , std::regex_constants::icase
		DIR *dir;
		struct dirent *ent;
		if ((dir = opendir (cqpfolder.c_str())) != NULL) {
		  /* print all the files and directories within directory */
		  while ((ent = readdir (dir)) != NULL) {
		  	string fname = ent->d_name;
			if ( preg_match(fname, "(.*)_xidx.rng", &m) ) cout << m[1] << endl;
		  }
		  closedir (dir);
		} else {
		  /* could not open directory */
		  perror ("");
		  cout << "Failed to read CQP folder " << cqpfolder << endl;
		}
	} else if ( preg_match (sql, "describe (.*)", &m ) ) { // TODO: , std::regex_constants::icase
		string tmp = m[1];
		DIR *dir;
		struct dirent *ent;
		if ((dir = opendir (cqpfolder.c_str())) != NULL) {
		  /* print all the files and directories within directory */
		  while ((ent = readdir (dir)) != NULL) {
		  	string fname = ent->d_name;
			if ( preg_match(fname, tmp+"_(.*).avs", &m) ) cout << m[1] << endl;
		  }
		  closedir (dir);
		} else {
		  /* could not open directory */
		  perror ("");
		  cout << "Failed to read CQP folder " << cqpfolder << endl;
		}
	} else if ( sql != "" ) {
		cout << "Unknown SQL command: " << sql << endl;
	};
	
};

class cqlresult {
	// A named CQL result, set by Sub = [cqlquery]
	// holds a vector of matching position (sets)

	public:
	string name;
	string cql;
	string global;
	string within;
	string sortfield;
	map<string, int> named;
	vector<cqlmatch> match;
	vector<cqltok> condlist;
	vector<string> toklist;
	map<int, map<int, int> > condarray;
	
	void parsecql (string tmp) {
		// Run a CQL search to create a result vector

		vector<string> m; 
		cql = tmp; string partname;

		if ( debug ) { cout << "Treating CQL " << name << ": " << tmp << endl; };

		if ( preg_match (cql, "^(.*) +within +(.*)$", &m ) ) {
			cql = m[1]; within = m[2];
			// TODO: translate this into min and max (and have those being used)
		};
		if ( preg_match (cql, "^(.*) +:: +(.*)$", &m ) ) {
			cql = m[1]; global = m[2];
		};
		
		std::vector<std::vector<std::string> > tokres = preg_match_all (cql, "((?:@|[^ ]+:)?)\\[([^\\]]+)\\]([*+?]?)");

		vector<string> parts; 
		int maxrank = 0; named["best"] = -1; // to determine the best init condition
		// Logic:  (you can always see what was used as the best token)
		// - start with target
		// - never start with a wildcard token
		// - avoid starting with a crossref att=b.att
		// - att="string" best, followed by att="regex*"
		// TODO: prefer large attribute sets, and long regexp definitions
		
		// Analyse each CQL token in turn
        for ( int i=0; i<tokres.size(); i++ ) {
			std::vector<std::string> iter = tokres[i];

			toklist.push_back(iter[0]);

        	string tmp = iter[1];
        	string conds = iter[2]; 
        	string wildcard = iter[3]; 

			string partname;
        	if ( tmp == "@" ) { 
        		named["target"] = i; 
        		partname = "target";
        	}; 
			if ( preg_match (tmp, "^ *([^ ]+):$", &m ) ) {	
				partname = m[1];
        		named[partname] = i; 
        	}; 

        	parts.clear();
        	if ( conds != "" ) parts = split( conds, "&" );
			for ( int k=0; k<parts.size(); k++ ) {
				string part = parts[k];
				cqltok newtok;
				newtok.partnr = i;
				newtok.idx = k; // pointless, but needed for now
				newtok.partname = partname;
				newtok.wildcard = wildcard;
				if ( preg_match (part, "(.*) *(%[^ ]+)", &m ) ) {
					newtok.flags = m[2];
					part = m[1];
				}; 
				int rank = 0;
				if ( partname == "target" ) rank = 11; // We get 10 points for being target (unless impossible, start with the target) 
				if ( wildcard != "" ) rank = -20; // Never start with a wildcard token
				if ( preg_match (part, "(.*?) *(!?[=<>]) *(.*)", &m ) ) {
					// Attribute matching string or regex
					newtok.left = trim(m[1]); 
					newtok.matchtype = m[2];
					newtok.right = trim(m[3]);

					if ( preg_match ( newtok.left, " \"([^\"]+)\"", &m ) ) {
						rank += 3; // 5 points for a string/regex match left
						newtok.leftstring = m[1]; 
					} else if ( preg_match (newtok.left, "(.*\\..*)", &m ) ) {
						newtok.leftfield.setfld(newtok.left, named);
						rank -= 5; // do not start with a relation to another attribute
					} else {
						rank += 5; // 5 points for an attribute match left
					};
					if ( preg_match (newtok.right, "\"([^\"]+)\"", &m ) ) {
						newtok.rightstring = m[1];
						if ( preg_match(newtok.right, ".*[*+?].*") || newtok.flags.find("c") != std::string::npos ) rank += 3; // 3 points for a regex match
						else rank += 5; // 5 points for a string match 
					} else if ( preg_match (newtok.right, "(.*\\..*)") ) {
						newtok.rightfield.setfld(newtok.right, named);
						rank -= 5; // do not start with a relation to another attribute
					}; 
				};
				if ( rank > maxrank ) { 
					named["best"] = condlist.size();
					maxrank = rank;
				};
				newtok.rawdef = trim(part);
				condlist.push_back(newtok);
				condarray[i][k] = condlist.size() - 1; // keep conditions ordered by toknr
			};
		};
		
		// Initialize on the best condition
		int best = named["best"]; 
		if ( best != -1 ) {
			cqltok ctok = condlist[best];
			int i = ctok.partnr;
			int k = ctok.idx;
			string part = ctok.rawdef;
			string wildcard = ctok.wildcard;
			string partname = ctok.partname;
			string flags = ctok.flags;
			string left = trim(ctok.left);
			string matchtype = ctok.matchtype;
			string right = trim(ctok.right);

			// Do the initial lookup
			vector<int> tmp;
			if ( preg_match (right, " *\"([^\"]+)\"*", &m ) ) {
				string word = m[1]; string attname = left;
				if ( preg_match(word, ".*[*+?].*") || flags.find("c") != std::string::npos ) {	
					// regex match initialization - slower
					vector<int> tmpi = regex2idx(attname, word, flags);
					for ( int j=0; j<tmpi.size(); j++ ) {
						vector<int> tmpp = idx2pos(attname, tmpi[j]);
						tmp.insert(tmp.end(), tmpp.begin(), tmpp.end());
					};
				} else {
					tmp = idx2pos(attname, str2idx(attname, word));

					if ( mwe != "" ) {
						string mweatt = mwe + "_" + attname;
						dbout << "Looking for MWE: " << mweatt << " = " << word << endl;
						vector<int> mwems = regex2ridx(mweatt, word);
						for ( int j=0; j<mwems.size(); j++ ) {
							vector<int> tmprng = ridx2rng(mweatt, mwems[j]);
							dbout << "MWE result: " << mwems[j] << " = " << tmprng[0] << " = " << ridx2str(mweatt, mwems[j])  << " / " << pos2str(attname, tmprng[0]) << endl;
						};
					};
				};
								
			} else if ( preg_match (part, "^ *(.*?) *(!?[=<>]) *(.*\\..*)$", &m ) ) {
				// TODO: Initialize with a cqlfld condition?
			} else {
				// TODO: Initialize with a comparison condition?
			};

			// Populate the result vector
			for ( int j=0; j<tmp.size(); j++ ) {
				cqlmatch tmp2;
				int dopos = tmp[j];
				tmp2.named["match"] = dopos;
				tmp2.named["matchend"] = dopos;
				tmp2.options[i].push_back(dopos);
				tmp2.ind = 1; // What does this do?
				if ( within != "" ) { // TODO: this does not work right now
// 					vector<int> ranges = pos2ridx(within, dopos);
// 					// This only uses the first range the item belongs to - not supported for overlapping ranges
// 					vector<int> range = ridx2rng(within, ranges[0]);
// 					tmp2.min=range[0];
// 					tmp2.max=range[1];
				}; 
				if ( partname != "" ) tmp2.named[partname] = dopos; // Redundant?
				match.push_back(tmp2);
			};
			
			condlist[best].done = true;
			if ( debug ) { cout << "Size for init " << part << ": " << match.size() << endl; };

		};
				
		// From the best position, go right
		for ( int ca = best; ca<condarray.size(); ca++ ) {
			for (int cc=0; cc<condarray[ca].size(); cc++) {
				if ( debug ) cout << "Checking " << condlist[condarray[ca][cc]].left << endl; // TODO: this somehow prevents the system from going wild - why??
				checkcond(condlist[condarray[ca][cc]], &match);
			};
        };
		// From the best position, go left
		for ( int ca = best-1; ca>-1; ca-- ) {
			for (int cc=0; cc<condarray[ca].size(); cc++) {
				if ( debug ) cout << "Checking " << condlist[condarray[ca][cc]].left << endl; // TODO: this somehow prevents the system from going wild - why??
				checkcond(condlist[condarray[ca][cc]], &match);
			};
        };
        
        // Check global conditions
		parts = split( global, "&" );
		for ( int k=0; k<parts.size(); k++ ) {
			string part = parts[k]; string left; string right; string leftval; string rightval; string matchtype;
			if ( preg_match (part, "^ *(.*?) *(!?[=<>]) *(.*?) *$", &m ) ) {
				// Comparison between cqlfld 
				left = m[1]; matchtype = m[2]; right = m[3];

				cqlfld leftfield; cqlfld rightfield; 
				if ( preg_match (left, "(.*\\..*)", &m ) ) {
					leftfield.setfld(left, named);
				};
				if ( preg_match (right, "(.*\\..*)", &m ) ) {
					rightfield.setfld(right, named);
				};

				for( vector<cqlmatch>::iterator it2 = match.begin(); it2 != match.end(); ) {

					// Calculate the left value
					if ( leftfield.rawbase != "" ) {
						leftval = leftfield.value(*it2);
					} else if ( preg_match (left, " *\"([^\"]+)\"*", &m ) ) {
						// a (regex) string
						leftval = m[1];
					} else {
						// A position
						leftval = it2->named[m[1]];
					};
					
					// Calculate the right value
					if ( rightfield.rawbase != "" ) {
						rightval = rightfield.value(*it2);
					} else if ( preg_match (right, " *\"([^\"]+)\"*", &m ) ) {
						// a (regex) string
						rightval = m[1];
					} else {
						// A position
						rightval = it2->named[m[1]];
					};
					
					if ( !resmatch(leftval, rightval, matchtype) ) { 
						it2 = match.erase(it2);
					} else {
						++it2;
					};
				};
			} else if ( preg_match (part, "^ *(.*?) contains (.*?) *$", &m ) ) {
			
			} else if ( part != "" ) {
				cout << "Unknown global condition: " << part << endl;
			};			
		};
		
		// Calculate actual positions (for wildcards)
		for( vector<cqlmatch>::iterator it2 = match.begin(); it2 != match.end(); it2++ ) {
			vector<int> poslist;
			
			// First run - fix the positions with a unique value
			for ( int i=0; i<condlist.size(); i++ ) {
				vector<int> options = it2->options[i];
				
				if ( options.size() == 1 ) { 
					poslist.push_back(options[0]);
				} else {
					poslist.push_back(-2); 	// Postpone choosing
				};
			};
			
			int j=0; int minval = poslist[j]; while ( minval == -1 && j< poslist.size() ) { j++; minval=poslist[j]; };
			it2->named["match"] = minval;
			j=poslist.size()-1; int maxval = poslist[j]; while ( maxval == -1 && j>0 ) { j--; maxval=poslist[j]; };
			it2->named["matchend"] = maxval;	
						
			for( map<string,int>::iterator it = named.begin(); it != named.end(); it++ ) {
				string name = it->first; int idx = it->second;
				it2->named[name] = poslist[idx];
			};
		};
		
	};
	
	int size() {
		// Return the size of the result vector
		return match.size();
	};

	void sort ( string field ) {
		// Sort the result vector
		
		sortfield = field; vector<string> m; bool desc;

		if ( preg_match (field, "(.*) (DESC|descending)", &m ) ) {
			sortfield = m[1];
			desc = true;
		};		

		cqlfld sortfld;
		sortfld.setfld(sortfield, named);
		if ( debug ) { cout << "Sorting on " << sortfld.fld << " - base " << sortfld.rawbase << endl; };
		std::sort (match.begin(), match.end(), Matchsorter(sortfld) );
		
		if ( desc ) {
			vector< cqlmatch > swapped( match.rbegin(), match.rend() );
			swapped.swap(match);
		};
	};

	void index ( string field ) {
		// Add an index to the result vector
		// TODO : implement this
		
		string index; vector<string> m; 
		if ( preg_match (field, "(.*) = (.*)", &m ) ) {
			field = m[2];
			index = m[1];
		};		
		
		vector< vector<map<int, int> > > newidx;
			
		if ( preg_match (field, " *([^ ]+) expand to (.*)", &m ) ) {
			string basepos = m[1]; string satt = m[2];
			
			for ( int i=0; i<match.size(); i++ ) {
				
			};
						
		};
		
	};
	
	map<string,int> stats(string cqlfld, string stattype = "" ) {
		// Print out statistical measures for the result vector

		string rawstats = cqlfld;
		map<string,int> resultlist; string opts; vector<string> show;
		string measure; string type; vector<string> m; string dir; int context; int span;
		
		string filename = cqpfolder + "word.corpus";
		FILE* stream = fopen(filename.c_str(), "rb"); 
		fseek(stream, 0, SEEK_END); corpussize = ftell(stream)/4; 
		if ( debug ) { cout << "Corpus size: " << corpussize << endl; };
		fclose(stream);
		
		if ( preg_match (cqlfld, " *(.*?) +:: +(.*)", &m ) ) {
			cqlfld = m[1];
			opts = m[2];
		};		
		
		vector<string> flds = split( cqlfld, " " );
		
		if ( preg_match (opts, ".*measure:([^ ]+).*", &m ) ) {
			measure = m[1];
		} else { measure = "mutinf"; };
		
		if ( preg_match (opts, ".*type:([^ ]+).*", &m ) ) {
			type = m[1];
		} else if ( stattype != "" ) { 
			type = stattype;
		} else { type = "collocations"; };
		
		if ( preg_match (opts, ".*show:([^ ]+).*", &m ) ) {
			string showflds = m[1];
			show = split( showflds, "," );
		};
		
		if ( preg_match (opts, ".*context:([-+]?)(\\d+).*", &m ) ) {
			dir = m[1];
			context = intval(m[2]);
			span = context;
			if ( dir == "" ) span = 2*context;
		} else if ( preg_match (opts, ".*context:([^ ]+).*", &m ) ) {
			// To allow context:head
			dir = m[1];
			context = 0;
			span = 1;
		} else { 
			dir = "+";
			context = 1; 
			span = 1;
		};
		
		if ( verbose ) { cout << "Statistics : " << type <<  " on " << cqlfld << " - measure " << measure << endl; };
		
		if ( type == "collocations"  ) {
			string value; string sep; 
			map<string,int> counts;
			
			if ( verbose ) cout << "Use: [" << cqlfld << "] + " << opts << endl;
			
			// Gather the context 
			for ( int i=0; i<match.size(); i++ ) {
				if ( dir == "+" || dir == "" ) {
					for ( int j=0; j<context; j++ ){
						int pos = match[i].named["matchend"]+1+j;
						value = ""; sep = "";
						for (int k=0; k<flds.size(); k++ ) {
							value += sep + pos2str(flds[k], pos); sep = "\t";
						};
						counts[value]++;
					};
				};
				if ( dir == "-" || dir == "" ) {
					for ( int j=0; j<context; j++ ){
						int pos = match[i].named["match"]-1-j;
						value = ""; sep = "";
						for (int k=0; k<flds.size(); k++ ) {
							value += sep + pos2str(flds[k], pos); sep = "\t";
						};
						counts[value]++;
					};
				};
				if ( dir != "" && dir != "-" && dir != "+" ) {
					// context based on relpos
					string relbase = "match"; // relpos of match or target when specified
					if ( named["target"] ) { relbase = "target"; };
					int pos = match[i].named[relbase];

					value = ""; sep = "";
					for (int k=0; k<flds.size(); k++ ) {
						int relpos = pos2relpos(dir, pos);
						value += sep + pos2str(flds[k], relpos); 	
												
						sep = "\t";
					};
					for (int k=0; k<show.size(); k++ ) {
						value += sep + pos2str(show[k], pos); 	
												
						sep = "\t";
					};
					counts[value]++;
				};				
			};
			
			// Calculate the statistics
			int csize; int obs; string coll; float exp; float calc; string posval;
			vector<int> poslist; vector<string> vallist; string coll1;
			
			pugi::xml_node resfld;
			pugi::xml_node resnode;
			pugi::xml_document resfile;
			if ( output == "xml" ) {
				resfile.append_child("results");
				resfile.first_child().append_attribute("cql") = cql.c_str();
				resfile.first_child().append_attribute("stats") = rawstats.c_str();
			} else if ( output == "json" ) {
				cout << "[[{'id':'coll', 'label':'{%Collocates}'}, {'id':'obs', 'label':'{%Observed}'}, {'id':'csize', 'label':'{%Total}'}, {'id':'exp', 'label':'{%Expected}'}";
				if ( measure == "chi2" || measure == "all" ) {
					cout << ", {'id':'chi2', 'label':'{%Chi-Square}'}";
				};
				if ( measure == "mutinf" || measure == "all" ) {
					cout << ", {'id':'mutinf', 'label':'{%Mutual Information}'}";
				};
				cout << "]," << endl;
			};
			for (std::map<string,int>::iterator it=counts.begin(); it!=counts.end(); ++it) {
				obs = it->second; 
				coll = it->first;

				if ( output == "xml" ) {
					resnode = resfile.first_child().append_child("result");
				};
				
				if ( flds.size() > 1 || show.size() > 0 ) {
					vallist = split( coll, "\t" );
					coll1 = vallist[0];
				} else {
					coll1 = coll;
				};
				
				int idx = str2idx(flds[0], coll1); // Check the index on the first field
				
				if ( idx != -1 ) { // Safety measure - leave out lexicon.idx we cannot find
					
					if ( flds.size() > 1 ) {
						poslist = idx2pos(flds[0], idx); // Set initially as count just for col 1, then throw pos where other cols do not match
						csize = 0;

						for (int i=1; i<poslist.size(); i++ ) {
							bool checked = true;
							for (int j=1; j<flds.size(); j++ ) {
								if ( pos2str(flds[j], idx) != vallist[i] ) {
									checked = false;
								};
							};
							if ( checked ) {
								csize++;
							};
						};
					} else {
						csize = idx2cnt(flds[0], idx);
					};
					
					float part = (float)csize/corpussize;
					exp = (float)match.size()*part*(float)span; 

					if ( output == "xml" ) {
						resfld = resnode.append_child("tab");
						resfld.append_attribute("key") = "collocate";
						resfld.append_attribute("value") = coll.c_str();
						
						resfld = resnode.append_child("tab");
						resfld.append_attribute("key") = "observed";
						resfld.append_attribute("value") = obs;
						
						resfld = resnode.append_child("tab");
						resfld.append_attribute("key") = "total";
						resfld.append_attribute("value") = csize;
						
						resfld = resnode.append_child("tab");
						resfld.append_attribute("key") = "expected";
						resfld.append_attribute("value") = exp;
						
					} else if ( output == "json" ) {
						coll = replace_all(coll, "'", "\\'");
						cout << "['" << coll << "'," << obs << "," << csize  << "," << exp;
					} else {	
						cout << coll << "\t" << obs << "\t" << csize  << "\t" << exp;
					};
					
					if ( measure == "chi2" || measure == "all" ) {
						calc = (float)(obs-exp)*(float)(obs-exp)/exp;
						if ( output == "xml" ) {
							resfld = resnode.append_child("tab");
							resfld.append_attribute("key") = "chi2";
							resfld.append_attribute("value") = calc;
						} else if ( output == "json" ) {
							cout << "," << calc;
						} else {
							cout << "\t" << calc;
						};
					};
					if ( measure == "mutinf" || measure == "all" ) {
						calc = log( (float)(obs * corpussize) / (float)( match.size() * csize * span ) ) / log(2);
						if ( output == "xml" ) {
							resfld = resnode.append_child("tab");
							resfld.append_attribute("key") = "mutinf";
							resfld.append_attribute("value") = calc;
						} else if ( output == "json" ) {
							cout << "," << calc;
						} else {
							cout << "\t" << calc;
						};
					};
					if ( output == "xml") {
					} else if ( output == "json" ) {
						cout << "]," << endl;
					} else {
						cout << endl;
					};
				} else if ( debug ) { cout << "Discarding (idx not found): " << coll1 << endl; };
			};
			if ( output == "xml" ) {
				resfile.print(cout);
			} else if ( output == "json" ) {
				cout << "]" << endl;;
			};
						
		} else if ( type == "keywords"  ) {

			string value; string sep; 
			map<string,int> counts;
			
			if ( verbose ) cout << "Use: [" << cqlfld << "] + " << opts << endl;
						
			// Calculate the statistics
			int obs; int refcnt; string item; int csize; float refsize; float calc; string posval;
			vector<int> poslist; vector<string> vallist; string item1;
			
			pugi::xml_node resfld;
			pugi::xml_node resnode;
			pugi::xml_document resfile;
			if ( output == "xml" ) {
				resfile.append_child("results");
				resfile.first_child().append_attribute("cql") = cql.c_str();
				resfile.first_child().append_attribute("stats") = rawstats.c_str();
			} else if ( output == "json" ) {
				cout << "[[{'id':'item', 'label':'{%Item}'}, {'id':'obs', 'label':'{%Observed}'}, {'id':'refcnt', 'label':'{%Reference count}'}, {'id':'csize', 'label':'{%Corpus Size}'}, {'id':'refsize', 'label':'{%Reference Size}'}";
				if ( measure == "loglike" || measure == "all" ) {
					cout << ", {'id':'loglike', 'label':'{%Log Likelihood}'}";
				};
				cout << "]," << endl;
			};
			for (std::map<string,int>::iterator it=counts.begin(); it!=counts.end(); ++it) {
				obs = it->second; 
				item = it->first;

				if ( output == "xml" ) {
					resnode = resfile.first_child().append_child("result");
				};
				
				if ( flds.size() > 1 || show.size() > 0 ) {
					vallist = split( item, "\t" );
					item1 = vallist[0];
				} else {
					item1 = item;
				};
				
				int idx = str2idx(flds[0], item1); // Check the index on the first field
				
				if ( idx != -1 ) { // Safety measure - leave out lexicon.idx we cannot find
					
					if ( flds.size() > 1 ) {
						poslist = idx2pos(flds[0], idx); // Set initially as count just for col 1, then throw pos where other cols do not match
						csize = 0;

						for (int i=1; i<poslist.size(); i++ ) {
							bool checked = true;
							for (int j=1; j<flds.size(); j++ ) {
								if ( pos2str(flds[j], idx) != vallist[i] ) {
									checked = false;
								};
							};
							if ( checked ) {
								csize++;
							};
						};
					} else {
						csize = idx2cnt(flds[0], idx);
					};
					
					float part = (float)csize/corpussize;

					if ( output == "xml" ) {
						resfld = resnode.append_child("tab");
						resfld.append_attribute("key") = "item";
						resfld.append_attribute("value") = item.c_str();
						
						resfld = resnode.append_child("tab");
						resfld.append_attribute("key") = "observed";
						resfld.append_attribute("value") = obs;
						
						resfld = resnode.append_child("tab");
						resfld.append_attribute("key") = "refcnt";
						resfld.append_attribute("value") = refcnt;
						
						resfld = resnode.append_child("tab");
						resfld.append_attribute("key") = "total";
						resfld.append_attribute("value") = csize;
						
						resfld = resnode.append_child("tab");
						resfld.append_attribute("key") = "refsize";
						resfld.append_attribute("value") = refsize;
						
					} else if ( output == "json" ) {
						item = replace_all(item, "'", "\\'");
						cout << "['" << item << "'," << obs << "," << refcnt   << "," << csize  << "," << refsize;
					} else {	
						cout << item << "\t" << obs << "\t" << refcnt << "\t" << csize  << "\t" << refsize;
					};

					if ( measure == "loglike" || measure == "all" ) {
						int a; int b; int c; int d;
						a = obs; b = refcnt; c = csize; c = refsize;

						float e1 = c*(a+b)/(c+d);
						float e2 = d*(a+b)/(c+d);
						calc = 2*( (a*log(a/e1)) + (b*log(b/e2)) );
						if ( output == "xml" ) {
							resfld = resnode.append_child("tab");
							resfld.append_attribute("key") = "loglike";
							resfld.append_attribute("value") = calc;
						} else if ( output == "json" ) {
							cout << "," << calc;
						} else {
							cout << "\t" << calc;
						};
					};
					if ( output == "xml") {
					} else if ( output == "json" ) {
						cout << "]," << endl;
					} else {
						cout << endl;
					};
				} else if ( debug ) { cout << "Discarding (idx not found): " << item1 << endl; };
			};
			if ( output == "xml" ) {
				resfile.print(cout);
			} else if ( output == "json" ) {
				cout << "]" << endl;;
			};					
			
		} else {
			cout << "Unknown statistics type: " << type << endl;
		};
		
		return resultlist;
	};
	
	void group ( string fields ) {
		// Print out frequency data for the result vector
	
		vector<cqlfld> cqlfieldlist; string groupfld;
		map<string,int> counts;
		vector<string> fieldlist; 	vector<string> m;  string sep; string value;

		// Calculate the corpus size for WPM measurements
		// TODO: for sattributes, this should count with global conditions
		string filename = cqpfolder + "word.corpus";
		FILE* stream = fopen(filename.c_str(), "rb"); 
		fseek(stream, 0, SEEK_END); corpussize = ftell(stream)/4; 
		if ( debug ) { cout << "Corpus size: " << corpussize << endl; };
		fclose(stream);
		
		if ( preg_match (fields, "([^ ]+) ([^ ]+) by ([^ ]+) ([^ ]+)", &m ) ) {
			// For compatibility with CQP 
			// convert "group match word by match lemma" to "group match.word match.lemma"
			string tmp1 = m[1]; string tmp2 = m[2]; string tmp3 = m[3]; string tmp4 = m[4];
			fields = tmp1+"."+tmp2+" "+tmp3+"."+tmp4;
		};		
		
		fieldlist = split( fields, " " );

		for ( int j=0; j< fieldlist.size(); j++ ) {
			cqlfld groupfld;
			groupfld.setfld(fieldlist[j], named);
			cqlfieldlist.push_back(groupfld);
		}
		if ( debug ) { cout << "Grouping by " << fields << endl; };
		for ( int i=0; i<match.size(); i++ ) {
			sep = ""; value = "";
			for ( int j=0; j<cqlfieldlist.size(); j++ ) {
				value += sep + cqlfieldlist[j].value(match[i]);
				sep = "\t";
			};
			counts[value]++;
		};
		if ( output == "xml" ) {
			pugi::xml_document resfile;
			resfile.append_child("results");
			resfile.first_child().append_attribute("cql") = cql.c_str();
			resfile.first_child().append_attribute("group") = groupfld.c_str();
			pugi::xml_node resfld;
			vector<string> resflds;
			for (std::map<string,int>::iterator it=counts.begin(); it!=counts.end(); ++it) {
				pugi::xml_node resnode = resfile.first_child().append_child("result");
				string key = it->first;
				int value = it->second;
				resflds = split( key, "\t" );
				for ( int j=0; j<resflds.size(); j++ ) {
					string valuefld = resflds[j];
					pugi::xml_node resfld = resnode.append_child("tab");
					resfld.append_attribute("key") = cqlfieldlist[j].rawfld.c_str();
					resfld.append_attribute("val") = valuefld.c_str();
				};
				resfld = resnode.append_child("count");
				resfld.append_attribute("val") = value;
			};
			resfile.print(cout);
		} else if ( output == "json" ) {
			cout << "[[";
			for ( int j=0; j<cqlfieldlist.size(); j++ ) {
				cout << "{'id':'" << cqlfieldlist[j].fld << "', 'label':'{%" << cqlfieldlist[j].fldname << "}'}, ";
			};
			bool withglobals = false;
			if ( fieldlist.size() == 1 && fields.find("_") != -1 ) { withglobals = true; }; // Check whether we have an sattribute
			if ( withglobals ) {
				cout << " {'id':'count', 'label':'{%Count}', 'type':'number'}, {'id':'tot', 'label':'{%Total}', 'type':'number'},  {'id':'wpm', 'label':'{%Words per million}', 'type':'number'} ]," << endl;
			} else {
				cout << " {'id':'count', 'label':'{%Count}', 'type':'number'}, {'id':'wpm', 'label':'{%WPM}', 'type':'number'} ]," << endl;
			};
			for (std::map<string,int>::iterator it=counts.begin(); it!=counts.end(); ++it) {
				string cnti = it->first; string item = "";
				vector<string> its = split(cnti, "\t");
				for ( int i=0; i<its.size(); i++ ) {
					string valname = valstring(cqlfieldlist[i], its[i]); 
					valname = replace_all(valname, "'", "\\'");
					item += "'" + valname + "', "; 
				};
				int count = it->second;
				if ( withglobals ) {
					int selsize = 0;
					string dofld = cqlfieldlist[0].fld;// this only works for 1 field
					vector<int> tmpi = regex2ridx(dofld, cnti); 
					for ( int j=0; j<tmpi.size(); j++ ) {
						int rngsize = ridx2cnt(dofld, tmpi[j]);
						selsize += rngsize;
					};
					if ( selsize > 0 ) {
						float wpm = ((float)count/selsize)*1000000;
						cout << "[" << item << " " << count << ", " << selsize << ", " << wpm << "]," << endl;
					};
				} else {
					float wpm = ((float)count/corpussize)*1000000;
					cout << "[" << item << " " << count << ", " << wpm << "]," << endl;
				};
			};
			cout << "]" << endl;
		} else {
			for (std::map<string,int>::iterator it=counts.begin(); it!=counts.end(); ++it) {
				std::cout << it->first << "\t" << it->second << endl;
			};
		};
	};
	
	void tabulate ( string fields ) {
		// Print out the result vector
	
		if ( verbose ) { cout << "Tabulating " << name << " on " << fields << endl; };
		vector<string> m;  string sep;
		vector<string> fieldlist = split( fields, " " );

		vector<cqlfld> cqlfieldlist;
		for ( int j=0; j<fieldlist.size(); j++ ) {
			if ( fieldlist[j] == "" ) { continue; };
			cqlfld cqlfield;
			cqlfield.setfld(fieldlist[j], named);
			cqlfieldlist.push_back(cqlfield);
		};

		if ( output == "xml" ) {
			pugi::xml_document resfile;
			resfile.append_child("results");
			resfile.first_child().append_attribute("cql") = cql.c_str();
			resfile.first_child().append_attribute("tab") = fields.c_str();
			resfile.first_child().append_attribute("size") = size();
			for ( int i=0; i<match.size(); i++ ) {
				pugi::xml_node resnode = resfile.first_child().append_child("result");
				for ( int j=0; j<cqlfieldlist.size(); j++ ) {
					string value = cqlfieldlist[j].value(match[i]);
					pugi::xml_node resfld = resnode.append_child("tab");
					resfld.append_attribute("key") = cqlfieldlist[j].rawfld.c_str();
					resfld.append_attribute("val") = value.c_str();
				};
			};
			resfile.print(cout);
		} else if ( output == "json" ) {
			cout << "[[";
			for ( int j=0; j<cqlfieldlist.size(); j++ ) {
				cout << "{'id':'" << cqlfieldlist[j].rawfld << "', 'label':'{%" << cqlfieldlist[j].fldname << "%} (" << cqlfieldlist[j].rawbase << ")'}, ";
				sep = ", ";
			};
			cout << "]," << endl;

			for ( int i=0; i<match.size(); i++ ) {
				sep = "";
				cout << "[";
				for ( int j=0; j<cqlfieldlist.size(); j++ ) {
					string value = cqlfieldlist[j].value(match[i]);
					value = replace_all(value, "'", "\\'");
					cout << sep << "'" << value << "'"; 
					sep = ", ";
				};
				cout << "]," << endl;
			};
			cout << "]" << endl;
		} else {
			for ( int i=0; i<match.size(); i++ ) {
				sep = "";
				for ( int j=0; j<cqlfieldlist.size(); j++ ) {
					string value = cqlfieldlist[j].value(match[i]);
					cout << sep << value; 
					sep = "\t";
				};
				cout << endl;
			};
		};
		
	};
		
	void xidx ( string options) {
		// Print out XML fragments for the result vector
		
		// TODO: expand to s
		
		for ( int i=0; i<match.size(); i++ ) {
			string xidx = rng2xml(match[i].named["match"], match[i].named["match"]);
			xidx = replace_all(xidx, "\n", " ");
			cout << xidx << endl;
		};
		
	};

		
	void info() {
		// Print out info about the result vector
	
		cout << "Subcorpus " << name << " : " << cql << " :: " << global << endl; // << " within " << within << endl;
		cout << "Size: " << size() << endl;
		cout << "Sorted on: " << sortfield << endl;
		// cout << "Named tokens: " << named << endl;
	};
};

int read_network_number ( int toread, FILE *stream ) {
	// Read a number from a CWB file
	int i;
	int offset = 4*toread;
	fseek(stream,offset,SEEK_SET);
	fread(&i, 4, 1, stream);
	return ntohl(i);
};

string read_file_range ( int from, int to, string filename ) {
	// Read file range
	// int buf[BUFSIZE];
	char chr = 'x';

	// char * result; 
	string value;
	int i, bufpos;
	
	FILE* stream = fopen ( filename.c_str() , "rb" );

	if ( stream == NULL ) { 
		if ( verbose ) { cout << "File could not be opened: " << filename << endl; };
		return "";
	};

	// Reverse when needed
	if ( to < from ) { int tmp = to; to=from; from=tmp; };

	// Wind to from position
	if ( debug > 3 ) { cout << "Seeking for " << from << endl; };
	fseek ( stream , from , SEEK_SET );

	if ( debug > 3 ) { cout << "Getting length " << to-from << endl; };
	value = ""; int last = ftell(stream);
    while ( ftell(stream) < to ) {
    	chr = fgetc(stream);
		if ( debug > 6 ) { cout << last << " : " << chr << endl; };
	 	if ( ftell(stream) < to ) { value = value + chr; };
	 	if ( last == ftell(stream) ) { return ""; }; // Not advancing, return nothing, prob out of file range
		last = ftell(stream);
	 };
	if ( debug > 6 ) { cout << "String " << value << endl; };

	fclose(stream);
	
	return value;
};

string idx2str ( string attname, int idx ) {
	// Find the string for an IDX of an attribute 
	string word; int i;
	FILE* stream; string filename;

	// Deterime the range
	filename = cqpfolder + attname + ".lexicon.idx";
	if ( !streamopen(&stream, filename) ) return "";
	
	int offset = 4*idx;
	rewind(stream);
	fseek(stream,offset,SEEK_SET);
	fread(&i, 4, 1, stream);
	int start = ntohl(i);
	//fclose(stream);
	
	filename = cqpfolder + attname + ".lexicon";
	if ( !streamopen(&stream, filename) ) return "";

	// Read the range
	char strval[1000];
	rewind(stream);
	fseek(stream,start,SEEK_SET);
   	fgets(strval, 400, stream); // We do not need the end - the \0 or eof will terminate
   	// fclose(stream);
	
	return strval;
};

string ridx2str ( string attname, int idx ) {
	// Find the string for an IDX of an attribute 
	string word; int i;
	FILE* stream; string filename;

	filename = cqpfolder + attname + ".avx";
	if ( !streamopen(&stream, filename) ) return "";

	// Deterime the range
	int offset = 4*(idx*2+1); // we have two lines per entry, and the first one is useless
	fseek(stream,offset,SEEK_SET);
	fread(&i, 4, 1, stream);
	int start = ntohl(i);
	// fclose(stream);
	
	filename = cqpfolder + attname + ".avs";
	if ( !streamopen(&stream, filename) ) return "";

	// Read the range
	char strval[1000];
	fseek(stream,start,SEEK_SET);
   	fgets(strval, 400, stream); // We do not need the end - the \0 or eof will terminate
   	// fclose(stream);
	
	return strval;
};

string pos2str ( string attname, int pos ) {
	// Find the string for an attribute at a given corpus position
	int idx; FILE* stream; 

	if ( pos == -1 ) return "";

	string filename = cqpfolder + attname + ".corpus";
	if ( !streamopen(&stream, filename) ) return "";

	idx = read_network_number(pos, stream);
	//fclose(stream);
	
	return idx2str(attname, idx);
};

int idx2cnt ( string attname, int idx ) {
	// return the count for in index on attname
 	FILE* stream; 
 
	string filename = cqpfolder + attname + ".corpus.cnt";
	if ( !streamopen(&stream, filename) ) return -1;

	int cnt = read_network_number(idx,stream);
	// fclose(stream);
	return cnt;
};

vector<int> ridx2rng ( string attname, int ridx ) {
 	FILE* stream; 
 	vector<int> range; // Actually just a pair
 	
	string filename = cqpfolder + attname + ".rng";
	streamopen(&stream, filename);
	
	int i = 2*ridx;
	int start = read_network_number(i, stream); range.push_back(start);
	int end = read_network_number(i+1, stream); range.push_back(end);
	
	return range;	 	
};

vector<int> pos2ridx ( string attname, int pos ) {
	// return the index for a range containing a position (an attname)
	vector<int> idx;
	
 	FILE* stream; 

	string filename = cqpfolder + attname + ".idx";

	if ( streamopen(&stream, filename, false) ) {
		// If we happen to have a satt.idx file, read quickly - also, will always be unique
		idx.push_back(read_network_number(pos,stream));
	} else {
	 	FILE* stream2; 
		string filename2 = cqpfolder + attname + ".rng";
		streamopen(&stream2, filename2);
		if ( stream2 == NULL ) { cout << "Failed to open RNG file: " << filename2 << endl; return idx; };
		int start = -1; int end = -1; 
		fseek(stream2, 0, SEEK_END); int max = ftell(stream2)/4;
		for ( int i=0; i<max; i=i+2 ) {
			start = read_network_number(i, stream2);
			end = read_network_number(i+1, stream2);
			
			if ( start < pos && end > pos ) { 
				idx.push_back(i/2); 
			}; // TODO: gather rather than replace
		};
	};
	//fclose(stream);
	return idx;
};

string rng2xml( int pos1, int pos2 ) {
	string filename; FILE * file; int rpos;

	filename = cqpfolder + "/text_id.idx";
	file = fopen ( filename.c_str() , "rb" );
	int textid1 = read_network_number(pos1, file);
	int textid2 = read_network_number(pos2, file);
	fclose(file);

	// Check that the positions belong to the same file
	// TODO: it merely returns, whereas it should throw an exception
 	if ( textid1 != textid2 ) { 
		if ( verbose ) { cout << "Corpus positions " << pos1 << " and " << pos2 << " do not belong to the same XML file" << endl;  };
		return "";
 	};	

	string xmlfile = ridx2str("text_id", textid1);

	filename = cqpfolder + "/xidx.rng";
	file = fopen ( filename.c_str() , "rb" );
	int rpos1 = read_network_number(pos1*2,file);
	int rpos2 = read_network_number(pos2*2+1,file);
	fclose(file);

	string value = read_file_range(rpos1, rpos2, xmlfile);

	return value;
};

wstring towstring (const string s) {
	// Cast a string to a wstring
	
    wstring wsTmp(s.begin(), s.end());

    wstring ws = wsTmp;

    return ws;
}

vector<int> regex2ridx (string attname, string word ) {
	// Return the vector of indices matching a regex on range attname
	FILE* stream; string strval; int i; 
	vector<int> match; vector<int> avx;
	map<int,bool> ofs;

   	// TODO: make this use rangename.rnx

	string filename = cqpfolder + attname + ".avs";
	if ( !streamopen(&stream, filename) ) return match;
	rewind(stream);

	// First, get the avx numbers
	ifstream myfile( filename.c_str() ); int idx = 0;
	if (myfile) {
		while ( getline( myfile, strval, '\0' ) )  {
			if ( resmatch(word, strval, "==") ) { // TODO: Make this do more liberal comparison
				ofs[idx] = true;
			};
			idx = myfile.tellg();
			i++;
		}
		myfile.close();
	} else {
		cout << "Failed to read " << filename << endl;
	}
	
   	// Now get the ranges for the avx index
	filename = cqpfolder + attname + ".avx";
	if ( !streamopen(&stream, filename) ) return match;
	
	fseek(stream, 0, SEEK_END); int max = ftell(stream)/2; 
	rewind(stream);
	int rnx = 0; int rng = 0; int j=0;
	while ( j < max ) {
		fread(&i, 4, 1, stream);
		rng = ntohl(i);
		fread(&i, 4, 1, stream);
		rnx = ntohl(i);

		if ( ofs[rnx] ) {
			match.push_back(rng);
		}; 
		j = j+4;
	};
   	
	return match;
	
};

int ridx2cnt ( string attname, int ridx ) {
	FILE* stream;
	string filename = cqpfolder + attname + ".rng";
	if ( !streamopen(&stream, filename) ) return 0;
	
	int seek = 2*ridx;
	int start = read_network_number(seek, stream);
	int end = read_network_number(seek+1, stream);
	int cnt = end - start + 1;
	
	return cnt;
};

int str2idx ( string attname, string word ) {
	// Find the IDX number in an attribute for a specific string 
	int pos = -1; string match = ""; FILE* stream;
	int min = 0; int max; int seek; int idx;
	
	string filename = cqpfolder + attname + ".lexicon.srt";
	if ( !streamopen(&stream, filename) ) return -1;

	fseek(stream, 0, SEEK_END); max = ftell(stream)/4; int lastseek = -1;
	while ( min < max ) { // With the /2 it gets stuck in the middle
		seek = int((max-min)/2) + min; if ( lastseek == seek  ) { 
			if ( seek > min ) {
				seek = seek - 1; 
			} else {
				return -1;
			};
		};
		lastseek = seek;
		idx = read_network_number(seek, stream);
		match = idx2str(attname, idx);
		// Cast to wstring cos otherwise string comparison does not match CWB sorting
		// if ( debug ) { cout << "Seeking " << word << " on " << seek << " = " << match << " <= " << min << "-" << max << endl; };
		if ( match == word ) {
			return idx;
		} else if ( towstring(match) < towstring(word) ) {
			min = seek;
		} else if ( towstring(match) > towstring(word) ) {
			max = seek;
		} else {
			return -1;
		};
	};
   	//fclose(stream);

	return -1;
};

int str2cnt ( string attname, string word ) {
	// Frequency for a string in attributes
	return idx2cnt(attname, str2idx(attname, word));
};

vector<int> str2pos ( string attname, string word ) {
	// Corpus matches for a string in attributes
	return idx2pos(attname, str2idx(attname, word));
};

vector<int> regex2idx ( string attname, string restr, string flags = "" ) {
	// Return the vector of indices matching a regex on attname

	vector<int> idxset; int idx = 0;
	string word;

	string strname = cqpfolder + attname + ".lexicon";
	
	ifstream myfile( strname.c_str() );
	if (myfile) {
		while ( getline( myfile, word, '\0' ) )  {
			if ( preg_match(word, restr, flags) ) {
				idxset.push_back(idx);
			}
			idx++;
		}
		myfile.close();
	} else {
		cout << "Failed to read " << strname << endl;
	}
  
  	return idxset;
};

vector<int> idx2pos (string attname, int idx ) {
	// Return the position for an index for attname

	vector<int> posset;
	string word; int i; int offset; int pos;
	FILE* stream;  string filename;

	// Deterime the range
	filename = cqpfolder + attname + ".corpus.rdx";
	if ( !streamopen(&stream, filename) ) return posset;

	offset = 4*idx;
	fseek(stream,offset,SEEK_SET);
	fread(&i, 4, 1, stream);
	int start = ntohl(i);
	fread(&i, 4, 1, stream);
	int end = ntohl(i);
	//fclose(stream);
		
		
	filename = cqpfolder + attname + ".corpus.rev";
	if ( !streamopen(&stream, filename) ) return posset;

	offset = 4*start; 
	fseek(stream,offset,SEEK_SET);
	for ( int i = start; i < end; i++ ) {
		fread(&pos, 4, 1, stream);
		posset.push_back(ntohl(pos));
	};
	
	return posset;
};

int pos2relpos ( string attname, int pos ) {
	// Return the related position for a position (on attname)
	FILE* stream;  string filename;

 	// Use the TT .pos file to link to a related word (typically the position of the head)
	filename = cqpfolder + attname + ".corpus.pos";
	if ( !streamopen(&stream, filename) ) return -1;

	int headnum = read_network_number(pos, stream);
	//fclose(stream); 
	
	return headnum;
};

void cqlparse ( string cql ) {
	// Parse a CQL query
	if ( debug && cql != "" ) { cout << "Parsing a CQL command: " << cql << endl; };
	
	vector<string> m; 
	cql = trim(cql); string subname;
	if ( cqpfolder == "/" && preg_match (cql, "([^ ]+)", &m ) ) {
		corpusname = m[1];
		settings.attribute("corpusname").set_value(corpusname.c_str());
		str2lower(corpusname); vector<string> m;
		string filename = regfolder + corpusname;
		ifstream myfile( filename.c_str() ); string line;
		if (myfile) {
			while ( getline( myfile, line ) )  {
				if ( preg_match(line, "HOME (.*)", &m ) ) {
					cqpfolder = m[1];
				};
			}
			myfile.close();
		} else {
			cout << "Failed to read corpus definition: " << filename << endl;
		}
		int lastch = cqpfolder.length() - 1; 
		if ( cqpfolder[lastch] != '/' ) { cqpfolder += "/"; };
		cout << "New corpus folder: " << cqpfolder << endl;
	} else if ( cql == "show corpora" ) {
		DIR *dir;
		struct dirent *ent;
		if ((dir = opendir (regfolder.c_str())) != NULL) {
		  /* print all the files and directories within directory */
		  while ((ent = readdir (dir)) != NULL) {
		  	string fname = ent->d_name;
			if ( fname.substr(0,1) != "." ) cout << fname << endl;
		  }
		  closedir (dir);
		} else {
		  /* could not open directory */
		  perror ("");
		  cout << "Failed to read registry folder" << endl;
		}
	} else if ( preg_match (cql, "([^ ]+) *= (.*)", &m ) ) {
		if ( debug ) { cout << "Setting a new subcorpus: " << m[1] << endl; };
		subname = m[1];
		cqlresult newcql;
		newcql.name = subname;
		newcql.parsecql(m[2]);
		subcorpora[subname] = newcql;
		last = subname;
	} else if ( preg_match (cql, "set (.*) (.*)", &m ) ) {
		// set CQL options from input		
		string var = m[1]; string val = m[2];
		if ( var == "mwe" ) {
			mwe = val;
		} else if ( var == "output" ) {
			output = val;
		} else if ( var == "kleene" ) {
			kleene = intval(val);
		};
	} else if ( preg_match (cql, "([\"\[].*)", &m ) ) {
		if ( last != "" ) {
			subname = last;
		} else {
			subname = "Anon";
		};
		cqlresult newcql;
		newcql.name = subname;
		newcql.parsecql(m[1]);
		subcorpora[subname] = newcql;
		last = subname;
	} else if ( preg_match (cql, "([^ ]+)(.*)", &m ) ) {
		string command = m[1];
		string rest = trim(m[2]); 
		
		// Determine the name of the subcorpus
		if ( preg_match (rest, "([^ ]+)(.*)", &m ) ) {
			string tmp = trim(m[1]);
			if ( subcorpora.find(tmp) != subcorpora.end() ) {
				subname = tmp;
				rest = trim(m[2]);
			};
		};
		if ( subname == "" || subname == "Last" ) {
			if ( last != "" ) {
				subname = last;
			} else {
				subname = "Anon";
			};
		};
		last = subname;

		if ( command == "tabulate" ) {
			subcorpora[subname].tabulate(rest);
		} else if ( command == "sort" ) {
			subcorpora[subname].sort(rest);
		} else if ( command == "group" ) {
			subcorpora[subname].group(rest);
		} else if ( command == "stats" ) {
			subcorpora[subname].stats(rest);
		} else if ( command == "coll" || command == "collocations"  ) {
			subcorpora[subname].stats(rest, "collocations");
		} else if ( command == "keys" || command == "keywords"  ) {
			subcorpora[subname].stats(rest, "keywords");
		} else if ( command == "info" ) {
			subcorpora[subname].info();
		} else if ( command == "xidx" ) {
			subcorpora[subname].xidx(rest);
		} else if ( command == "index" ) {
			subcorpora[subname].index(rest);
		} else if ( command == "size" ) {
			cout << subcorpora[subname].size() << endl; 
		} else {
			cout << "Unrecognized command: " << cql << endl;
		};
	} else if (cql != "" ) {
		cout << "Unrecognized command: " << cql << endl;
	};

};

int main(int argc, char *argv[]) {
	int i; string word; string attname;
	
	logfile.append_child("cqp");
	settings = logfile.first_child().append_child("settings");	

	// Check when we started
	time_t beginT = clock(); time_t tm = time(0);
	string tmp = ctime(&tm);
	logfile.first_child().append_attribute("starttime") = tmp.substr(0,tmp.length()-1).c_str();	

	// Define short command-line options
	map<string, vector<string> > shortopt;
	shortopt["D"].push_back("corpusname"); shortopt["D"].push_back("1");
	shortopt["r"].push_back("registry"); shortopt["r"].push_back("1");
	shortopt["b"].push_back("kleene"); shortopt["b"].push_back("1");
	shortopt["e"].push_back("prompt"); 

	// Read in all the command-line arguments
	for ( int i=1; i< argc; ++i ) {
		string argm = argv[i]; string akey;
		
		if ( argm.substr(0,2) == "--" ) {
			int spacepos = argm.find("=");
			
			if ( spacepos == -1 ) {
				string akey = argm.substr(2);
				settings.append_attribute(akey.c_str()) = "1";
			} else {
				string akey = argm.substr(2,spacepos-2);
				string aval = argm.substr(spacepos+1);
				settings.append_attribute(akey.c_str()) = aval.c_str();
			};
		} else if ( argm.substr(0,1) == "-" ) {
			string tmp = argm.substr(1);
			if ( shortopt.find(tmp) == shortopt.end() ) {
				cout << "Unknown option: " << tmp << endl;
			} else {
				akey = shortopt[tmp][0];
				if ( shortopt[tmp][1] != "" ) {
					string aval = argv[i+1];
					settings.append_attribute(akey.c_str()) = aval.c_str();
					++i;
				} else {
					settings.append_attribute(akey.c_str()) = "1";
				};
			};
		};		
		
	};
	
	
	if ( settings.attribute("registry") != NULL ) { 
		regfolder = settings.attribute("registry").value();
	} else {
		regfolder = "/usr/local/share/cwb/registry/";
	};

	if ( settings.attribute("kleene") != NULL ) { 
		kleene = atoi(settings.attribute("kleene").value());
	} else {
		kleene = 10;
	};

	// Read the settings.xml file where appropriate - by default from ./Resources/settings.xml
	string settingsfile;
	string folder;
	if ( settings.attribute("settings") != NULL ) { 
		settingsfile = settings.attribute("settings").value();
	} else {
		folder = ".";
		settingsfile = "./Resources/settings.xml";
	};
    if ( xmlsettings.load_file(settingsfile.c_str())) {
    	if ( verbose ) { cout << "- Using settings from " << settingsfile << endl;   }; 	
    };
	
	// Parse some of the settings
	if ( settings.attribute("output") != NULL   ) {
		output = settings.attribute("output").value();
	};

	// Parse some of the settings
	if ( settings.attribute("mwe") != NULL   ) {
		mwe = settings.attribute("mwe").value();
	};

	corpusname = "[no corpus]";
	if ( settings.attribute("cqpfolder") != NULL   ) {
		cqpfolder = settings.attribute("cqpfolder").value();
		corpusname = cqpfolder;
	} else if ( settings.attribute("corpusname") != NULL   ) {
		corpusname = settings.attribute("corpusname").value();
		str2lower(corpusname); vector<string> m;
		string filename = regfolder + corpusname;
		ifstream myfile( filename.c_str() ); string line;
		if (myfile) {
			while ( getline( myfile, line ) )  {
				if ( preg_match(line, "HOME (.*)", &m ) ) {
					cqpfolder = m[1];
				};
			}
			myfile.close();
		} else {
			cout << "Failed to read corpus definition: " << filename << endl;
		}
		if ( debug ) cout << "Corpus folder: " << cqpfolder << endl;
	} else if ( file_exists("cqp/word.corpus") ) {
		cqpfolder = "cqp/";
		corpusname = "tt-cqp";
	}; 
		int lastch = cqpfolder.length() - 1; 
		if ( cqpfolder[lastch] != '/' ) { cqpfolder += "/"; };

	bool keepinput;
	if ( settings.attribute("keepinput") != NULL   ) {
		keepinput = true;
	};
		
	if ( settings.attribute("attname") != NULL   ) {
		attname = settings.attribute("attname").value();
	} else {
		attname = "word";
	};
	
	string cmd; string mode;
	if ( settings.attribute("mode") != NULL   ) {
		mode = settings.attribute("mode").value();
	} else {
		mode = "cql";
	};

	if ( settings.attribute("debug") != NULL ) { debug = atoi(settings.attribute("debug").value()); };
	if ( settings.attribute("verbose") != NULL ) { verbose = true; };
	if ( settings.attribute("prompt") != NULL ) { prompt = true; };
		
	// Read commands from STDIN 
	vector<string> fields; string line;
	if ( mode ==  "cql" ) {
		// CQL command are separated by ;
		if ( prompt ) { cout << corpusname << "> "; };
		while ( getline( cin, line, ';' ) && line != "exit" ) {
			line = trim(line);
			if ( line == "exit" ) { break; };
			cqlparse(line);
			if ( prompt ) { cout << corpusname << "> "; };
		}	
		if ( prompt ) { cout << "Bye" << endl; };
	} else if ( mode ==  "sql" ) {
		// SQL command are separated by ;
		if ( prompt ) { cout << "tt-sql> "; };
		while ( getline( cin, line, ';' ) && line != "exit" ) {
			line = trim(line);
			if ( line == "exit" ) { break; };
			sqlparse(line);
			if ( prompt ) { cout << "tt-sql> "; };
		}	
		if ( prompt ) { cout << "Bye" << endl; };
	} else if ( mode ==  "pos2rel" ) {
		// Give back the corpus position of the head (or other related id)
		while ( getline( cin, line ) && line != "exit" ) {
			if (keepinput) { cout << line << "\t"; };
			cout << pos2relpos(attname, intval(line)) << endl;
		};
	} else if ( mode == "str2cnt" ) {
		// Give back the count for a set of srings
		while ( getline( cin, line ) && line != "exit" ) {
			if (keepinput) { cout << line << "\t"; };
			cout << str2cnt(attname, line) << endl;
		};
	} else if ( mode == "pos2str" ) {
		// Give back the string for a set of corpus positions (on attname)
		while ( getline( cin, line ) && line != "exit" ) {
			if (keepinput) { cout << line << "\t"; };
			if ( preg_match(line, "(\\d+) *- *(\\d+)") ) {
				fields = split( line, "-" );
				for ( int i=intval(fields[0]); i<intval(fields[1]); i++ ) {
					cout << pos2str(attname, i) << endl;
				};
			} else {
				cout << pos2str(attname, intval(line)) << endl;
			};
		};
	} else if ( mode ==  "dump" ) {
		// Dump a CWB file to stdout
		string fext = settings.attribute("file").value();
		string filename = cqpfolder + attname + "." + fext;
		cout <<  "Dump of " << filename << endl;
		FILE* stream = fopen(filename.c_str(), "rb"); 
		if (stream == NULL) {
			cout << "Failed to open: " << filename << endl;
		} else { 
			fseek(stream, 0, SEEK_END); int max = ftell(stream)/4;
			for ( int i=0; i<max; i++ ) {
				int val = read_network_number(i, stream);
				cout << i << "\t" << val;
				if ( fext == "lexicon.srt" || fext == "lexicon.idx" || fext == "corpus" ) {
					cout << "\t\"" << idx2str(attname, val) << "\"";
				} else if ( fext == "corpus.cnt" ) {
					cout << "\t\"" << idx2str(attname, i) << "\"";
				};
				cout << endl;
			};
			fclose(stream);
		};
	} else {
		cout << "Unknown mode: " << mode << endl;
	};
	
	if ( settings.attribute("log") != NULL  ) {
		time_t endT = clock();
		float elapsed = (float(clock())-float(endT))/float(CLOCKS_PER_SEC);
		logfile.first_child().append_attribute("time") = float2string(elapsed).c_str();	

		logfile.print(cout);
	};
	
	// Close all the file streams
	for (std::map<string,FILE*>::iterator it=files.begin(); it!=files.end(); ++it) {
		fclose(it->second);
	};

};
