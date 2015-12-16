#include <boost/algorithm/string.hpp>
#include <boost/tokenizer.hpp>
#include <boost/foreach.hpp>
#include <iostream>
#include <string.h>
#include <stdio.h>
#include <fstream>
#include <map>
#include <vector>
#include <dirent.h>
#include <sys/stat.h>
#include <arpa/inet.h>

#define BUFSIZE 4096

using namespace std;
using namespace boost;

map<string, string> clarg;
string cqpfolder;
string xmlfile;
int debug = 0;
bool verbose;
int context;

// Read network style
int read_network_number ( int position, FILE *stream ) {
	//int *buf;
	int N, i, bufpos;
	bufpos = position*sizeof(int);
	fseek ( stream , bufpos , SEEK_SET );
	if ( 1 == 2 ) {  cout << "New pos: " << ftell(stream) << endl; };

    fread(&N, sizeof(int), 1, stream);
	i = ntohl(N);        /* convert from CWB to internal format */
	if ( 1 == 2 ) {  cout << "Value: " << i << endl; };

	return i;
};

// Read file range
string read_file_range ( int from, int to, string filename ) {
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

// Read file until next null character
string read_file_tonull ( int from, FILE *stream ) {
	int buf[BUFSIZE];
	char chr = 'x';
	int i, bufpos;
	if ( debug > 3 ) { cout << "Seeking for " << from << endl; };
	fseek ( stream , from , SEEK_SET );

	string value = "";
    while ( chr != '\0' ) {
    	chr = fgetc(stream);
	 	value = value + chr;
	 };
	if ( debug > 3 ) { cout << "String " << value << endl; };


	return value;
};

string cwb_pos_2_val(string att, int pos) {
	
	string filename; FILE * file;

	filename = cqpfolder + "/" + att + ".corpus";
	file = fopen ( filename.c_str() , "rb" );
	int lexidx = read_network_number(pos,file);
	if ( debug > 3 ) { cout << "Lexicon position for " << pos << " in " << filename << " = " << lexidx << endl; };
	fclose (file);

	filename = cqpfolder + "/" + att + ".lexicon.idx";
	file = fopen ( filename.c_str() , "rb" );
	int lex1 = read_network_number(lexidx,file);
	int lex2 = read_network_number(lexidx+1,file);
	if ( debug > 3 ) { cout << "Lexicon index for " << lexidx << " in " << filename << " = " << lex1 << "-" << lex2 << endl; };
	fclose (file);

	filename = cqpfolder + "/" + att + ".lexicon";
	string value = read_file_range(lex1,lex2,filename);
	if ( debug > 3 ) { cout << "Lexicon value for " << lex1 << "-" << lex2 << " in " << filename << " = " << value << endl; };

	return value;
};

int cwb_rng_2_avx(string att, int pos) {
	string filename; FILE * file; int avx; int res = -1;
	int max = 1000000; // This should be the number of positions in the file

	filename = cqpfolder + "/" + att + ".rng";
	file = fopen ( filename.c_str() , "rb" );
	if ( debug > 3 ) { cout << "Getting range for " << pos << " in "  << filename << endl; };

	for ( int i=0; i<max; i=i+2 ) {
		int pos1 = read_network_number (i, file);
		if ( debug > 6 ) { cout << "Range 1 at " << i << " = " << pos1 << endl; };
		if ( pos1 <= pos ) {
			int pos2 = read_network_number (i+1, file);
			if ( debug > 6 ) { cout << "Range 2 at " << i << " = " << pos2 << endl; };
			if ( pos2 >= pos ) {
				if ( debug > 3 ) { cout << "Found a matching range " << pos1 << " - " << pos2 << " = " << i/2 << endl; };
				// If this is the text - store start and end for context expansion
				res = i/2;
				i=max+1;
			};
		};
	};
	fclose (file);
	
	return res;
};

string cwb_rng_2_val(string att, int pos) {
	
	string filename; FILE * file;

	int rangeidx = cwb_rng_2_avx(att, pos);

	filename = cqpfolder + "/" + att + ".avx";
	file = fopen ( filename.c_str() , "rb" );
	if ( debug > 3 ) { cout << "Reading range for " << filename << endl; };
	int avs = read_network_number(rangeidx*2+1,file);
	if ( debug > 3 ) { cout << "AVS position for " << pos << " in " << filename << " = " << avs  << endl; };
	fclose (file);

	filename = cqpfolder + "/" + att + ".avs";
	file = fopen ( filename.c_str() , "rb" );
	string value = read_file_tonull(avs,file);
	if ( debug > 3 ) { cout << "Lexicon value from " << avs << " in " << filename << " = " << value << endl; };
	fclose (file);

	return value;
};

string cwb_avx_2_val(string att, int rangeidx) {
	
	string filename; FILE * file;

	filename = cqpfolder + "/" + att + ".avx";
	file = fopen ( filename.c_str() , "rb" );
	if ( debug > 3 ) { cout << "Reading range for " << filename << endl; };
	int avs = read_network_number(rangeidx*2+1,file);
	if ( debug > 3 ) { cout << "AVS position for " << rangeidx << " in " << filename << " = " << avs  << endl; };
	fclose (file);

	filename = cqpfolder + "/" + att + ".avs";
	file = fopen ( filename.c_str() , "rb" );
	string value = read_file_tonull(avs,file);
	if ( debug > 3 ) { cout << "Lexicon value from " << avs << " in " << filename << " = " << value << endl; };
	fclose (file);

	return value;
};

void cwb_expand_rng( int posa, int posb, int *rpos1, int *rpos2, string att ) {
	string filename; FILE * file; int avx; int res = -1;
	int max; // This should be the number of positions in the file
	int pos1; int pos2; 

	filename = cqpfolder + "/" + att + ".rng";
	file = fopen ( filename.c_str() , "rb" );
	
	if ( debug > 3 ) { cout << "Getting enclosing range from " << filename << endl; };
	
	// Determine filesize so that we do not seek beyond it
	fseek(file, 0L, SEEK_END);
	max = ftell(file)/4; // we read 4 bytes
	
	for ( int i=0; i<max; i=i+2 ) {
		pos1 = read_network_number (i, file);
		if ( debug > 6 ) { cout << "Range 1 at " << i << " = " << pos1 << endl; };
		if ( pos1 <= posa ) {
			pos2 = read_network_number (i+1, file);
			if ( pos2 >= posa ) {
				if ( debug > 4 ) { cout << "Found a matching start range " << pos1 << " - " << pos2 << " = " << i/2 << endl; };
				//int rpos = i/2;
				int rpos = i;
				// Now look in att_xidx.rng to find the XML positions
				filename = cqpfolder + "/" + att + "_xidx.rng";
				FILE *file2 = fopen ( filename.c_str() , "rb" );
				*rpos1 = read_network_number(rpos,file2);
				*rpos2 = read_network_number(rpos+1,file2);
				if ( debug > 4 ) { cout << "XML range index " << i << " for " << posa << " in " << filename << " = " << pos1 << " - " << pos2  << " = " << rpos  << " = " << *rpos1 << " - " << *rpos2 << endl; };
				fclose (file2);
				i=max+1; // "last"
			} else if ( debug > 6 ) { cout << "Range too short - end = " << pos2 << endl; };

		};
	};
	
	if ( pos2 < posb ) {
		// Range not large enough, move second position out
		for ( int i=0; i<max; i=i+2 ) {
			pos1 = read_network_number (i, file);
			if ( debug > 4 ) { cout << "Range 2 at " << i << " = " << pos1 << endl; };
			if ( pos1 <= posb ) {
				pos2 = read_network_number (i+1, file);
				if ( pos2 >= posb ) {
					if ( debug > 4 ) { cout << "Found a matching end range " << pos1 << " - " << pos2 << " = " << i/2 << endl; };
					int rpos = i/2;
					*rpos2 = read_network_number(rpos+1,file);
					if ( debug > 4 ) { cout << "XML end range index for " << posb << " in " << filename << " = ... - " << pos2  << " = ... - " << *rpos2 << endl; };
					i=max+1; // "last"
				};
			};
		};
	}; 
	
	fclose (file);
};

string cwb_rng_2_xml(int pos1, int pos2) {
	
	string filename; FILE * file; int rpos;

	// Establish which XML file the pos range belongs to
	// xmlfile = cwb_rng_2_val("text_id", pos1); // The old way - too slow
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

	// get the name of the file
	xmlfile = cwb_avx_2_val("text_id", textid1);
	// get the range
	filename = cqpfolder + "/text_id.rng";
	file = fopen ( filename.c_str() , "rb" );
	int textrng0 = read_network_number(textid1*2, file);
	int textrng1 = read_network_number(textid2*2+1, file);
	fclose(file);

	int rpos1, rpos2; rpos2 = 0;
	
	if ( clarg.find("expand")  != clarg.end() ) {
		// Asked to expand to level X - try it
		if ( debug > 3 ) { cout << "Expanding " << pos1 << " - " << pos2 << " to " << clarg["expand"] << endl; };
		cwb_expand_rng(pos1, pos2, &rpos1, &rpos2, clarg["expand"]);
		if ( debug > 4 ) { cout << "Expanded positions: " << rpos1 << " - " << rpos2 << endl; };
	} else if ( context > 0 ) {
		// Expand by context, limited to the first and last position in the text range
		pos1 = max(pos1-context, textrng0);
		pos2 = min(pos2+context, textrng1);
		if ( debug > 3 ) { cout << "Expanding context with " << context << " to " << pos1 << " - " << pos2 << endl; };
	};
		
	if ( rpos2 == 0 ) {
		// Get simple corpus positions - lookup the corresponding XML positions
		if ( debug > 4 ) { cout << "Getting XML for " << pos1 << " - " << pos2 << endl; };
		filename = cqpfolder + "/xidx.rng";
		file = fopen ( filename.c_str() , "rb" );
		rpos1 = read_network_number(pos1*2,file);
		rpos2 = read_network_number(pos2*2+1,file);
		fclose(file);
		if ( debug > 0 ) { cout << "XML Range positions for " << pos1 << "-" << pos2 << " in " << filename << " = " << rpos1 << "-" << rpos2 << endl; };
	};		
	
	if ( verbose ) { cout << "XML filename: " << xmlfile << endl; };
	string value = read_file_range(rpos1, rpos2, xmlfile);
	
	if ( debug > 2 ) {
		cout << "--------------------------" << endl; 
	};
	
	return value;
};

int main (int argc, char *argv[]) {
	
	string avls[10]; int x=0;

	// Read in all the command-line arguments
	for ( int i=1; i< argc; ++i ) {
		string argm = argv[i];
		
		if ( argm.substr(0,2) == "--" ) {
			int spacepos = argm.find("=");
			
			if ( spacepos == -1 ) {
				string akey = argm.substr(2);
				clarg[akey] = "1";
			} else {
				string akey = argm.substr(2,spacepos-2);
				string aval = argm.substr(spacepos+1);
				clarg[akey] = aval;
			};
		} else { avls[x] = argm; x++; };	
	};
	
	if ( clarg.find("cqp") != clarg.end() ) { cqpfolder = clarg["cqp"];  } else { cqpfolder = "cqp"; };
	if ( clarg.find("filename") != clarg.end() ) { xmlfile = clarg["filename"];  };
	string patt = ""; if ( clarg.find("P") != clarg.end() ) { patt = clarg["P"];  };
	string satt = ""; if ( clarg.find("R") != clarg.end() ) { satt = clarg["R"];  };
	if ( clarg.find("verbose") != clarg.end() ) { verbose = true; };
	if ( clarg.find("debug") != clarg.end() ) { debug = atoi(clarg["debug"].c_str()); };

	if ( clarg.find("context") != clarg.end() ) { context = atoi(clarg["context"].c_str()); } else { context = 0; };
	
	if ( clarg.find("from") != clarg.end() ) { avls[0] = clarg["from"];  };
	if ( clarg.find("to") != clarg.end() ) { avls[1] = clarg["to"];  };

	
	if ( avls[0] == "" ) { 
		string input_line; list<string> inputs; int pos1; int pos2;
	    while(cin) {
    	    getline(cin, input_line);
			split(inputs, input_line, is_any_of("\t ")); 
			string arg1;string arg2;
			arg1 = inputs.front(); 
			inputs.pop_front();
			arg2 = inputs.front(); 
			
			try { 
				pos1 = atoi(arg1.c_str()); 
				pos2 = atoi(arg2.c_str()); 
			} catch( const std::exception& e ) { continue; }; 
			
			cout << cwb_rng_2_xml ( pos1, pos2 ) << endl;
    	};
	} else {
		// Items given on the command line
		if ( avls[1] == "" ) { avls[1] = avls[0]; };
		int pos1; int pos2;
	
		pos1 = atoi(avls[0].c_str());
		pos2 = atoi(avls[1].c_str());

		cout << cwb_rng_2_xml ( pos1, pos2 ) << endl;
	};
	
	// terminate
	return 0;
}