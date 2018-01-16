// TT-CQP - a custom version of CQP (from the Corpus Workbench) to address some issues in TEITOK
// (c) Maarten Janssen 2018

#include <boost/algorithm/string.hpp>
#include <iostream>
#include <sstream>  
#include <stdio.h>
#include <fstream>
#include <map>
#include <vector>
#include <dirent.h>
#include <sys/stat.h>
#include <arpa/inet.h>
#include "pugixml.hpp"
#include <regex>
#include <math.h>       /* pow */

using namespace std;
using namespace boost;

// TODO: Wish list
// attribute matching cqlfield ([word=a.word])
// xidx XML output
// within
// wildcard tokens (probably needs a restructuring of the match strategy)
// %cd
// cut
// subset (or restrict)
// intersection, join, difference
// match..matchend - or do ranges?

// Forward declarations
string pos2str(string a, int b);
vector<int> idx2pos(string a, int b);
int idx2cnt(string a, int b);
int str2idx(string a, string b);
int str2cnt ( string a, string b );
vector<int> regex2idx ( string a, string b );
int pos2relpos ( string attname, int pos );
string ridx2str ( string attname, int idx );
int pos2ridx ( string attname, int pos );
map<string,FILE*> files;

class cqlresult;
class cqlfld;

map<string, cqlresult> subcorpora;

int debug = 0;
bool test = false;
bool verbose = false;
string output;
string cqpfolder;
int corpussize;

pugi::xml_document logfile;
pugi::xml_node settings;
pugi::xml_node results;
pugi::xml_document xmlsettings;

bool resmatch ( string a, string b, string matchtype = "=" ) {
	// Check whether two strings "match" using various conditions
	
	// TODO: > < !> !< 
	// Maybe: >deps or >>
	
	if ( matchtype == "=" ) { // Regex ==
		return regex_match(a.c_str(), regex(b));
	} else if ( matchtype == "==" ) { // String == 
		return a == b;
	} else if ( matchtype == "!=" ) {
		if ( a == "" ) return false; // With the default regex non-match, we exclude empty strings
		return !regex_match(a.c_str(), regex(b));
	} else if ( matchtype == ">" ) {
		return stoi(a,nullptr,10) > stoi(b,nullptr,10);
	} else if ( matchtype == "<" ) {
		return stoi(a,nullptr,10) < stoi(b,nullptr,10);
	} else if ( matchtype == "!==" ) {
		return a != b;
	}
	return false;
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
	int sub1; int sub2; // For substring matches
	string rawbase;
	string base; // what to use as base (match, matchend, named, idfield)
	int offset; // where it is wrt base
	map<string,int> named; int keyword;
		
	void setfld ( string flditem, map<string,int> trg ) {
		// Initialize the field
	
		cmatch m;
		rawfld = flditem; 
		named = trg;

		sub1 = 0; sub2 = 0; 
		string tmp1; string tmp2;
		
		// substr(match)
		if ( regex_match (flditem.c_str(), m, regex("substr\\((.*?),(\\d+),(\\d+)\\)") ) ) {
			flditem = m[1]; tmp1 = m[2]; tmp2 = m[3];
			sub1 = stoi(tmp1,nullptr,10);  sub2 = stoi(tmp2,nullptr,10);
		};
		
		// match.fld
		if ( regex_match (flditem.c_str(), m, regex("([^ ]+)\\.([^ ]+)") ) ) {
			string posind = m[1]; string value;
			fld = m[2];
			if ( regex_match (posind.c_str(), m, regex("match\\[(-?\\d+)\\]") ) ) {
				base = "match"; rawbase = m[0];
				offset = stoi(m[1],nullptr,10);
			} else if ( regex_match (posind.c_str(), m, regex("matchend\\[(-?\\d+)\\]") ) ) {
				base = "matchend"; rawbase = m[0];
				offset = stoi(m[1],nullptr,10); 
			} else if ( posind == "match") { 
				base = posind; rawbase = base;
				offset = 0;
			} else if ( posind == "matchend") { 
				base = "matchend"; rawbase = base;
				offset = 0;
			} else if ( named[posind] ) { // named tokens (including target and keyword)
				base = posind;  rawbase = base;
				offset = 0;
			} else if ( file_exists(cqpfolder + posind + ".corpus.pos" ) ) { // with type=id fields using .pos
				offset = 0;
				base = posind;  rawbase = base;
			};			
		};
		
		// lookup the field definition and display name in settings.xml (when available)
		if ( regex_match (fld.c_str(), m, regex("([^ ]+)_([^ ]+)") ) ) {
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
								
	};
	
	string value(map<int,int> match, string matchype = "=" ) { // non-string results should just get casted back later
		// Return the result on the field for a match in the result vector
		
		cmatch m; string value;
		
		int posnum; int pos;
		if ( base == "match" || base == "" ) {
			pos = match[0];
		} else if ( base == "matchend" ) {
			pos = match[0] + match.size() - 1;
		} else if ( named[base] ) { // named tokens (including target and keyword)
			pos = match[named[base]];
		} else {
			int relbase = 0; // relpos of match or target when specified
			if ( named["target"] ) { relbase = named["target"]; };
			pos = pos2relpos(base, match[relbase]); 
		};
		posnum = pos + offset;
	
		// sattribute or pattribute
		if ( regex_match (fld.c_str(), m, regex("(.*)_(.*)") ) ) {
			int ridx = pos2ridx(fld, posnum); // TODO: this does not exist!
			value = ridx2str(fld, ridx); 
		} else {
			value = pos2str(fld, posnum); 
		};
		
		if ( sub2 > 0 ) {
			value = value.substr(sub1, sub2);
		};

		return value;
	};
	
};

// Sorting class for matches, so that we can pass arguments to the sort function (ie sortfield)
bool compareMatches(const map<int,int> t1, const map<int,int> t2, cqlfld sortfld ){
	return sortfld.value(t1) < sortfld.value(t2);
};
class Matchsorter {	
    cqlfld sortfld_;
	public:
		Matchsorter(cqlfld sortfld){ 
			sortfld_ = sortfld; 
		}
		bool operator()(map<int,int> t1, map<int,int> t2) const {
			return compareMatches( t1 , t2 , sortfld_ );
		}
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
	map<string,int> named;
	vector< map<int,int> > match;
	map<string, vector< vector<map<int, int> > > > indexes; // for each result, a name for a set of ranges (begin, end)
	
	void parsecql (string tmp) {
		// Run a CQL search to create a result vector

		cmatch m; 
		cql = tmp;

		if ( debug ) { cout << "Treating CQL " << name << ": " << tmp << endl; };

		if ( regex_match (cql.c_str(), m, regex("^(.*) +within +(.*)$") ) ) {
			cql = m[1]; within = m[2];
		};
		if ( regex_match (cql.c_str(), m, regex("^(.*) +:: +(.*)$") ) ) {
			cql = m[1]; global = m[2];
		};
		
		match_results<std::string::const_iterator> iter;
		string::const_iterator start = cql.begin() ; int i=0;
		vector<string> parts;
        while ( regex_search(start, cql.cend(), iter, regex("((?:@|[^ ]+:)?)\\[([^\\]]+)\\]( %.*)?")) ) {
        	string tmp = iter[1];
        	if ( tmp == "@" ) { 
        		named["target"] = i; 
        	}; 
			if ( regex_match (tmp.c_str(), m, regex("^ *([^ ]+):$") ) ) {	
				tmp = m[1];
        		named[tmp] = i; 
        	}; 
			
			// Add a position to the match list # TODO: for optional items should not be one to the right
			if ( i > 0 ) {
				for ( int j=0; j<match.size(); j++ ) {
					match[j][i] = match[j][i-1]+1;
				};
			};
			
        	string conds = iter[2]; parts.clear();
        	if ( conds != "" ) split( parts, conds, is_any_of( "&" ) );
			for ( int k=0; k<parts.size(); k++ ) {
				string part = parts[k];
				if ( regex_match (part.c_str(), m, regex("^ *(.*?) *(!?[=<>]) *(.*) *$") ) ) {
					// Attribute matching string or regex
					string left = m[1]; 
					string matchtype = m[2];
					string right = m[3];
					if ( k == 0 && i == 0 ) {
						vector<int> tmp;
						if ( regex_match (right.c_str(), m, regex(" *\"([^\"]+)\"*") ) ) {
							string word = m[1]; string attname = left;
							if ( regex_match(word.c_str(), regex(".*[*+?].*")) ) {	
								// regex match initialization - slower
								vector<int> tmpi = regex2idx(attname, word);
								for ( int j=0; j<tmpi.size(); j++ ) {
									vector<int> tmpp = idx2pos(attname, tmpi[j]);
									tmp.insert(tmp.end(), tmpp.begin(), tmpp.end());
								};
							} else {
								tmp = idx2pos(attname, str2idx(attname, word));
							};
						} else if ( regex_match (part.c_str(), m, regex("^ *(.*?) *(!?[=<>]) *(.*\\..*)$") ) ) {
							// TODO: Initialize with a cqlfld condition?
						} else {
							// TODO: Initialize with a comparison condition?
						};
						for ( int j=0; j<tmp.size(); j++ ) {
							map<int,int> tmp2; tmp2[0] = tmp[j];
							match.push_back(tmp2);
						};
					} else {
						vector< map<int,int> > tocheck = match;
						match.clear();
						string leftval; string rightval;
						for ( int j=0; j<tocheck.size(); j++ ) {
							map<int,int> tt = tocheck[j]; 
							// Calculate the value for the left-hand side
							if ( regex_match (left.c_str(), m, regex(" *\"([^\"]+)\"*") ) ) {
								// a (regex) string
								leftval = m[1];
							} else if ( regex_match (left.c_str(), m, regex(" *(.*\\..*) *") ) ) {
								// an cqlfld
								string cond = m[1]; 
								cqlfld cqlfield; cqlfield.setfld(cond, named);
								leftval = cqlfield.value(tt);
							} else {
								// an attribute
								leftval = pos2str(left,tt[i]);
							};
							// Calculate the value for the right-hand side
							if ( regex_match (right.c_str(), m, regex(" *\"([^\"]+)\"*") ) ) {
								// a (regex) string
								rightval = m[1];
							} else if ( regex_match (right.c_str(), m, regex(" *(.*\\..*) *") ) ) {
								// a cqlfld
								string cond = m[1];
								cqlfld cqlfield; cqlfield.setfld(cond, named);
								rightval = cqlfield.value(tt);
							} else {
								// an attribute
								rightval = pos2str(right,tt[i]);
							};
							// Keep value if the two sides match (using the matchype)
							if ( resmatch(leftval, rightval, matchtype) ) { 
								match.push_back(tt);
							};
						};
					};
				};
			};
            start = iter[0].second ; i++;
        }
        
        // Global conditions
		split( parts, global, is_any_of( "&" ) );
		for ( int k=0; k<parts.size(); k++ ) {
			string part = parts[k]; string left; string right; string leftval; string rightval; string matchtype;
			if ( regex_match (part.c_str(), m, regex("^ *(.*?) *(!?[=<>]) *(.*?) *$") ) ) {
				// Comparison between cqlfld 
				left = m[1]; matchtype = m[2]; right = m[3];
				// cout << "Checking " << left << " " << matchtype << " " << right << endl;
				vector< map<int,int> > tocheck = match;
				match.clear();
				for ( int j=0; j<tocheck.size(); j++ ) {
					map<int,int> tt = tocheck[j]; 
					// Calculate the left value
					if ( regex_match (left.c_str(), m, regex(" *\"([^\"]+)\"*") ) ) {
						// a (regex) string
						leftval = m[1];
					} else if ( regex_match (left.c_str(), m, regex(" *(.*\\..*) *") ) ) {
						// a cqlfld
						string cond = m[1];
						cqlfld cqlfield; cqlfield.setfld(cond, named);
						leftval = cqlfield.value(tt);
					} else {
						// what else can it be?
					};
					// Calculate the right value
					if ( regex_match (right.c_str(), m, regex(" *\"([^\"]+)\"*") ) ) {
						// a (regex) string
						rightval = m[1];
					} else if ( regex_match (right.c_str(), m, regex(" *(.*\\..*) *") ) ) {
						// a cqlfld
						string cond = m[1];
						cqlfld cqlfield; cqlfield.setfld(cond, named);
						rightval = cqlfield.value(tt);
					} else {
						// what else can it be?
					};
					if ( resmatch(leftval, rightval, matchtype) ) { 
						match.push_back(tt);
					};
				};
			};			
		};
		
	};
	
	int size() {
		// Return the size of the result vector
		return match.size();
	};

	void sort ( string field ) {
		// Sort the result vector
		
		sortfield = field; cmatch m; bool desc;

		if ( regex_match (field.c_str(), m, regex("(.*) (DESC|descending)") ) ) {
			sortfield = m[1];
			desc = true;
		};		

		cqlfld sortfld;
		sortfld.setfld(sortfield, named);
		if ( debug ) { cout << "Sorting on " << sortfld.fld << " - base " << sortfld.base << " - offset " << sortfld.offset << endl; };
		std::sort (match.begin(), match.end(), Matchsorter(sortfld) );
		
		if ( desc ) {
			vector< map<int,int> > swapped( match.rbegin(), match.rend() );
			swapped.swap(match);
		};
	};

	void index ( string indexname, string field ) {
		// Add an index to the result vector
		// TODO : implement this
		
		cmatch m; 
		vector< vector<map<int, int> > > newidx;
			
		if ( regex_match (field.c_str(), m, regex(" *([^ ]+) expand to (.*)") ) ) {
			string basepos = m[1]; string satt = m[2];
			
			for ( int i=0; i<match.size(); i++ ) {
				
			};
						
		};
		
	};
	
	map<string,int> stats(string cqlfld) {
		// Print out statistical measures for the result vector

		string rawstats = cqlfld;
		map<string,int> resultlist; string opts; vector<string> show;
		string measure; string type; cmatch m; string dir; int context; int span;
		
		string filename = cqpfolder + "word.corpus";
		FILE* stream = fopen(filename.c_str(), "rb"); 
		fseek(stream, 0, SEEK_END); corpussize = ftell(stream)/4; 
		if ( debug ) { cout << "Corpus size: " << corpussize << endl; };
		fclose(stream);
		
		if ( regex_match (cqlfld.c_str(), m, regex(" *(.*?) +:: +(.*)") ) ) {
			cqlfld = m[1];
			opts = m[2];
		};		
		
		vector<string> flds; split( flds, cqlfld, is_any_of( " " ) );
		
		if ( regex_match (opts.c_str(), m, regex(".*measure:([^ ]+).*") ) ) {
			measure = m[1];
		} else { measure = "mutinf"; };
		
		if ( regex_match (opts.c_str(), m, regex(".*type:([^ ]+).*") ) ) {
			type = m[1];
		} else { type = "collocations"; };
		
		if ( regex_match (opts.c_str(), m, regex(".*show:([^ ]+).*") ) ) {
			string showflds = m[1];
			split( show, showflds, is_any_of( "," ) );
		};
		
		if ( regex_match (opts.c_str(), m, regex(".*context:([-+]?)(\\d+).*") ) ) {
			dir = m[1];
			context = stoi(m[2],nullptr,10);
			span = context;
			if ( dir == "" ) span = 2*context;
		} else if ( regex_match (opts.c_str(), m, regex(".*context:([^ ]+).*") ) ) {
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
						int pos = match[i][match[i].size()-1]+1+j;
						value = ""; sep = "";
						for (int k=0; k<flds.size(); k++ ) {
							value += sep + pos2str(flds[k], pos); sep = "\t";
						};
						counts[value]++;
					};
				};
				if ( dir == "-" || dir == "" ) {
					for ( int j=0; j<context; j++ ){
						int pos = match[i][0]-1-j;
						value = ""; sep = "";
						for (int k=0; k<flds.size(); k++ ) {
							value += sep + pos2str(flds[k], pos); sep = "\t";
						};
						counts[value]++;
					};
				};
				if ( dir != "" && dir != "-" && dir != "+" ) {
					// context based on relpos
					int relbase = 0; // relpos of match or target when specified
					if ( named["target"] ) { relbase = named["target"]; };
					int pos = match[i][relbase];

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
					split( vallist, coll, is_any_of( "\t" ) );
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

			

		} else {
			cout << "Unknown statistics type: " << type << endl;
		};
		
		return resultlist;
	};
	
	void group ( string fields ) {
		// Print out frequency data for the result vector
	
		vector<cqlfld> cqlfieldlist; string groupfld;
		map<string,int> counts;
		vector<string> fieldlist; 	cmatch m;  string sep; string value;
		
		if ( regex_match (fields.c_str(), m, regex("([^ ]+) ([^ ]+) by ([^ ]+) ([^ ]+)") ) ) {
			// For compatibility with CQP
			string tmp1 = m[1];			string tmp2 = m[2];			string tmp3 = m[3];			string tmp4 = m[4];
			fields = tmp1+"."+tmp2+" "+tmp3+"."+tmp4;
		};		
		
		split( fieldlist, fields, is_any_of( " " ) );

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
				split( resflds, key, is_any_of( "\t" ) );
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
				cout << "{'id':'" << cqlfieldlist[j].fld << "', 'label':'{%" << cqlfieldlist[j].fldname << "%}'}, ";
			};
			cout << " {'id':'count', 'label':'{%Count}', type:number} ]," << endl;
			for (std::map<string,int>::iterator it=counts.begin(); it!=counts.end(); ++it) {
				string cnti = it->first;
				replace_all(cnti, "\t", "', '");
				cout << "['" << cnti << "', " << it->second << "]," << endl;
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
		vector<string> fieldlist; 	cmatch m;  string sep;
		split( fieldlist, fields, is_any_of( " " ) );

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


int pos2ridx ( string attname, int pos ) {
	// return the index for a range containing a position (an attname)
	
 	FILE* stream; 

	string filename = cqpfolder + attname + ".idx";

	int idx = -1;
	if ( streamopen(&stream, filename, false) ) {
		// If we happen to have a satt.idx file, read quickly
		cout << "Checking: " << filename << endl; 
		idx = read_network_number(pos,stream);
	} else {
	 	FILE* stream2; 
		string filename2 = cqpfolder + attname + ".rng";
		streamopen(&stream2, filename2);
		if ( stream2 == NULL ) { cout << "Failed to open RNG file: " << filename2 << endl; return -1; };
		int start = -1; int end = -1; 
		fseek(stream2, 0, SEEK_END); int max = ftell(stream2)/4;
		for ( int i=0; i<max; i=i+2 ) {
			start = read_network_number(i, stream2);
			end = read_network_number(i+1, stream2);
			
			if ( start < pos && end > pos ) { idx = i/2; }; // TODO: gather rather than replace
		};
	};
	//fclose(stream);
	return idx;
};

wstring towstring (const string s) {
	// Cast a string to a wstring
	
    wstring wsTmp(s.begin(), s.end());

    wstring ws = wsTmp;

    return ws;
}

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

vector<int> regex2idx ( string attname, string restr ) {
	// Return the vector of indices matching a regex on attname

	vector<int> idxset; int idx = 0;
	string word;

	regex re = regex(restr);

	string strname = cqpfolder + attname + ".lexicon";
	
	ifstream myfile( strname );
	if (myfile) {
		while ( getline( myfile, word, '\0' ) )  {
			if ( regex_match(word, re) ) {
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
	
	cmatch m; 
	if ( regex_match (cql.c_str(), m, regex(" *([^ ]+) *= *(.*)") ) ) {
		cqlresult newcql;
		newcql.name = m[1];
		newcql.parsecql(m[2]);
		subcorpora[m[1]] = newcql;
	} else if ( regex_match (cql.c_str(), m, regex("tabulate +([^ ]+) (.*)$") ) ) {
		subcorpora[m[1]].tabulate(m[2]);
	} else if ( regex_match (cql.c_str(), m, regex("sort +([^ ]+) (.*)$") ) ) {
		subcorpora[m[1]].sort(m[2]);
	} else if ( regex_match (cql.c_str(), m, regex("group +([^ ]+) (.*)$") ) ) {
		subcorpora[m[1]].group(m[2]);
	} else if ( regex_match (cql.c_str(), m, regex("stats +([^ ]+) (.*)$") ) ) {
		subcorpora[m[1]].stats(m[2]);
	} else if ( regex_match (cql.c_str(), m, regex("info +([^ ]+) (.*)$") ) ) {
		subcorpora[m[2]].info();
	} else if ( regex_match (cql.c_str(), m, regex("index +([^ ]+) ([^ ]+) = (.*)$") ) ) {
		subcorpora[m[1]].index(m[2], m[3]);
	} else if ( regex_match (cql.c_str(), m, regex("size +([^ ]+) (.*)$") ) ) {
		cout << subcorpora[m[1]].size() << endl; // TODO: What do we do with the second argument?
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

	// Read in all the command-line arguments
	for ( int i=1; i< argc; ++i ) {
		string argm = argv[i];
		
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
		};		
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

	if ( settings.attribute("cqpfolder") != NULL   ) {
		cqpfolder = settings.attribute("cqpfolder").value();
	} else if ( settings.attribute("corpusname") != NULL   ) {
		string corpusname = settings.attribute("corpusname").value();
		to_lower(corpusname); cmatch m;
		string filename = "/usr/local/share/cwb/registry/" + corpusname;
		ifstream myfile( filename ); string line;
		if (myfile) {
			while ( getline( myfile, line ) )  {
				if ( regex_match(line.c_str(), m, regex("HOME (.*)")) ) {
					cqpfolder = m[1];
				};
			}
			myfile.close();
		} else {
			cout << "Failed to read corpus definition: " << filename << endl;
		}
		cout << cqpfolder << endl;
	} else {
		cqpfolder = "cqp/";
	};

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
	
	
	// Read commands from STDIN 
	vector<string> fields; string line;
	if ( mode ==  "cql" ) {
		// CQL command are separated by ;
		while ( getline( cin, line, ';' ) && line != "exit" ) {
			trim(line);
			cqlparse(line);
		}	
	} else if ( mode ==  "pos2rel" ) {
		// Give back the corpus position of the head (or other related id)
		while ( getline( cin, line ) && line != "exit" ) {
			if (keepinput) { cout << line << "\t"; };
			cout << pos2relpos(attname, stoi(line,nullptr,10)) << endl;
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
			if ( regex_match(line, regex("(\\d+) *- *(\\d+)")) ) {
				split( fields, line, is_any_of( "-" ) );
				for ( int i=stoi(fields[0],nullptr,10); i<stoi(fields[1],nullptr,10); i++ ) {
					cout << pos2str(attname, i) << endl;
				};
			} else {
				cout << pos2str(attname, stoi(line,nullptr,10)) << endl;
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
		logfile.first_child().append_attribute("time") = to_string(elapsed).c_str();	

		logfile.print(cout);
	};
	
	// Close all the file streams
	for (std::map<string,FILE*>::iterator it=files.begin(); it!=files.end(); ++it) {
		fclose(it->second);
	};

};
