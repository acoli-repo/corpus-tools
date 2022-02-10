# wikidata.pl
# lookup NER data in wikidata
# Maarten Janssen, 2022

use XML::LibXML;
use Getopt::Long;
use utf8;
use LWP::Simple;
use LWP::UserAgent;
use POSIX qw(strftime);
use URI;

$\ = "\n"; $, = "\t";
binmode(STDOUT, ":utf8");

GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'test' => \$test, # test mode (do not save)
            'query=s' => \$query, # add additional revisionStmt
            'recid=s' => \$recid, # add additional revisionStmt
            'xml=s' => \$xmlfile, # output file
            'lang=s' => \$lang, # output file
            'type=s' => \$type, # record type
            );
            
if ( !$lang ) { $lang = "en"; };

if ( $query ) {

	foreach $qp ( split(";", $query) ) {
		($key, $val) = split("=", $qp);
		$q{$key} = $val;
	};
	
		$name = $q{'name'};
		if ( $name ) {
			   $rest .= "\n	SERVICE wikibase:mwapi {
				  bd:serviceParam wikibase:endpoint \"www.wikidata.org\";
					wikibase:api \"EntitySearch\";
					mwapi:search \"$name\"; 
					mwapi:language \"$lang\".
				  ?item wikibase:apiOutputItem mwapi:item.
				  }";
		};	

		$type = $q{'type'};
		if ( $type eq "persName" ) { $type = "person"; } 
		if ( $type eq "orgName" ) { $type = "org"; } 
		if ( $type eq "placeName" ) { $type = "place"; } 
		
		if ( $type eq "person" ) {
			$hyper = "Q5";
		} elsif ( $type eq "place" ) {
			$hyper = "Q82794";
		} elsif ( $type eq 'org' ) {
			$hyper = "Q43229";
 		};
 		if ( $hyper ) { 
			$rest .= "\n ?item wdt:P31/wdt:P279* wd:$hyper.";
 		};
	
		$birth = $q{'birth'};
		if ( $birth ) {
			$rest .= "\n  ?item p:P569/psv:P569 [ wikibase:timeValue ?birth ].\n  FILTER ( YEAR(?birth) = $birth ).";
		};
			
		$death = $q{'death'};
		if ( $death ) {
			$rest .= "\n  ?item p:P570/psv:P570 [ wikibase:timeValue ?death ].\n  FILTER ( YEAR(?death) = $death ).";
		};
			
		$sparql = "SELECT DISTINCT ?item ?itemLabel ?itemDescription WHERE {  
			$rest 
			SERVICE wikibase:label {bd:serviceParam wikibase:language \"$lang\".}
		} limit 200";
		
		if ( $debug ) { print $sparql; };
		$sparql =~ s/'/\\'/g;
		
		$base = "https://query.wikidata.org/sparql";
		$form{'content'} = $sparql; 
		$url = URI->new( $base );
		$url->query_form(
		  # All form pairs:
		  'query'   => $sparql,
		);
		
		$ua = LWP::UserAgent->new(ssl_opts => { verify_hostname => 1 });
		@headers = (
		  'User-Agent' => 'Mozilla/4.76 [en] (Win98; U)',
		  'Accept' => "application/sparql-results+xml",
		);

		$res = $ua->get($url, @headers);


		# $res = $ua->post($url, Content_Type => 'text/xml', Content => $sparql);
		# $res = $ua->post( $url, \%form );
		$raw = $res->decoded_content;
		
		if ( !$raw ) {
			print "No content from Wikidata";
			print $res->error_as_HTML;
			exit;
		};

		# $cmd= "curl --header \"Accept: application/sparql-results+xml\"  -G 'https://query.wikidata.org/sparql' --data-urlencode query='$sparql'";
		# $raw =  `$cmd`;


		$raw =~ s/xmlns=/xx=/g;
		$parser = XML::LibXML->new(); 
		eval {
			$data = $parser->load_xml(string => $raw, load_ext_dtd => 0);
		};
		if ( !$data ) {
			print "No result from Wikidata"; exit;
		};
		if ( $debug ) { print $data->toString; };

		@res = $data->findnodes("//result");
		
		if ( scalar @res == 1 ) {
			$tmp = $data->findnodes("//uri")->item(0);
			if ( $tmp ) { $tmp2 = $tmp->textContent; };
			( $recid = $tmp2 ) =~ s/http:\/\/www\.wikidata\.org\/entity\///;
			if ( $debug ) { print "Unique result: $recid"; };
		} else {
			print $raw;
		};	
};

if ( $recid ) {
		# Default to Wikidata 
		$raw = get("https://www.wikidata.org/w/api.php?action=wbgetentities&format=xml&ids=$recid");
		$raw =~ s/xmlns=/xx=/g;
		$parser = XML::LibXML->new(); 
		eval {
			$data = $parser->load_xml(string => $raw, { load_ext_dtd => 0, no_blanks => 1 } );
		};
		
		if ( $debug ) { 
			print $data->toString(1);
		};
		
		# Step 1 - determine the type of entity
		if ( !$type ) {
			$type = "entity";
			if ( $data->findnodes("//entity/property[\@id=\"P625\"]") ) { $type = "place"; };
			if ( $data->findnodes("//value[\@id=\"Q5\"]") ) { $type = "person"; };
			if ( $data->findnodes("//value[\@id=\"Q43229\"]") ) { $type = "org"; };
			if ( $debug ) { print "Record type: $type"; };
		};
				
		$nxml = "<$type id=\"wd-$recid\"></$type>";
		$rec = $parser->load_xml(string => $nxml, { load_ext_dtd => 0, no_blanks => 1 } );

		if ( $type eq "person" ) {
			
			addnode($data, "//entity/labels/label[\@language=\"$lang\"]/\@value", "persName", $rec, "Name");

			$sex = "";
			$tmp = $data->findnodes("//entity/claims/property[\@id=\"P21\"]/claim//value/\@id");
			if ( $tmp )  { 
				$tmp2 = $tmp->item(0)->value;
				if ( $tmp2 eq 'Q6581097' ) { $sex = "M";  };
				if ( $tmp2 eq 'Q6581072' ) { $sex = "F";  };
				
			};
			if ( $sex ) {
				if ( $debug ) { print "Sex: $sex ($tmp2)"; };
				$rec->firstChild->setAttribute("sex", $sex);
			};

			$tmp = $data->findnodes("//mainsnak[\@property=\"P569\"]//value[\@calendarmodel=\"http://www.wikidata.org/entity/Q1985727\"]/\@time");
			if ( $tmp ) { $bd = $tmp->item(0); };
			if ( $bd =~ /(\d\d\d\d-\d\d-\d\d)/ ) { 
				if ( $debug ) { print "Birth: $1"; };
				$bd = $1; 
			};
			$tmp = $data->findnodes("//mainsnak[\@property=\"P19\"]//value/\@id");
			if ( $tmp ) {
				$bid = $tmp->item(0)->value;
				$bpr = makeplace($bid);
				if ( $debug && $bpr ) { print "Birth place: ".$bpr->toString; };
			};
			if ( $bd || $bp ) {
				$newc = XML::LibXML::Element->new( "birth" );
				$rec->firstChild->appendChild($newc);
			};
			if ( $bd ) {
				$newc->setAttribute("when", $bd);
			};
			if ( $bpr ) {
				$newc->appendChild($bpr->firstChild);
			};

			$tmp = $data->findnodes("//mainsnak[\@property=\"P570\"]//value[\@calendarmodel=\"http://www.wikidata.org/entity/Q1985727\"]/\@time");
			if ( $tmp ) { $dd = $tmp->item(0); };
			if ( $dd =~ /(\d\d\d\d-\d\d-\d\d)/ ) { 
				if ( $debug ) { print "Death: $1"; };
				$dd = $1; 
			};
			$tmp = $data->findnodes("//mainsnak[\@property=\"P20\"]//value/\@id");
			if ( $tmp ) {
				$did = $tmp->item(0)->value;
				$dpr = makeplace($did);
				if ( $debug && $dpr ) { print "Death place: ".$dpr->toString; };
			};
			if ( $dd || $dp ) {
				$newc = XML::LibXML::Element->new( "death" );
				$rec->firstChild->appendChild($newc);
			};
			if ( $dd ) {
				$newc->setAttribute("when", $dd);
			};
			if ( $dpr ) {
				$newc->appendChild($dpr->firstChild);
			};

			
			$refs = "\n<link type=\"wikidata\" target=\"https://www.wikidata.org/wiki/$recid\"/>";
			foreach $ref ( $data->findnodes("//references//datavalue") ) {
				$rv = $ref->getAttribute('value'); 
				$rt = "";
				# if ( $rv =~ /viaf\.org/ ) { $rt = "viaf"; };
				# if ( $rv =~ /wikipedia\.org/ ) { $rt = "wikipedia"; };
				if ( $rt && !$done{"$rt-$rv"} ) {
					$rv = xmlprotect($rv);
					$refs .= "\n<link type=\"$rt\" target=\"$rv\"/>";
					$done{"$rt-$rv"} = 1;
				};
			};

			
		} elsif ( $type eq "place" ) {
		

			addnode($data, "//entity/labels/label[\@language=\"$lang\"]/\@value", "placeName", $rec, "Name");
			
			$country = proplookup("P17", $data);
			if ( $country ) {
				$tmp = XML::LibXML::Element->new( "country" );
				$tmp->appendChild($rec->createTextNode($country));
				$rec->firstChild->appendChild($tmp);
			};

			$tmp = $data->findnodes("//mainsnak[\@property=\"P625\"]//value");
			if ( $tmp ) {
				$geo = $tmp->item(0)->getAttribute('latitude')." ".$tmp->item(0)->getAttribute('longitude');
				$tmp = XML::LibXML::Element->new( "geo" );
				$tmp->appendChild($rec->createTextNode($geo));
				$rec->firstChild->appendChild($tmp);
			};

			$tmp = $data->findnodes("//entity/descriptions/description[\@language=\"$lang\"]");
			if ( $tmp ) {
				$desc = $tmp->item(0)->getAttribute('value');
				if ( $debug ) { print "Description: $desc"; };
				$newc = XML::LibXML::Element->new( "note" );
				$newc->appendChild($rec->createTextNode($desc));
				$rec->firstChild->appendChild($newc);
			}; 
			
			$refs = "\n<link type=\"wikidata\" target=\"https://www.wikidata.org/wiki/$recid\"/>";
			foreach $ref ( $data->findnodes("//references//datavalue") ) {
				$rv = $ref->getAttribute('value'); $rt = "";
				# if ( $rv =~ /viaf\.org/ ) { $rt = "viaf"; };
				# if ( $rv =~ /wikipedia\.org/ ) { $rt = "wikipedia"; };
				if ( $rt && !$done{"$rt-$rv"} ) {
					$rv = xmlprotect($rv);
					$refs .= "\n<link type=\"$rt\" target=\"$rv\"/>";
					$done{"$rt-$rv"} = 1;
				};
			};
			$geonames = vallookup("P1566", $data);
			if ( $geonames ) {
					$refs .= "\n<link type=\"geonames\" target=\"https://www.geonames.org/$geonames\"/>";
			};

		} elsif ( $type eq "org" ) {
		
			addnode($data, "//entity/labels/label[\@language=\"$lang\"]/\@value", "orgName", $rec, "Name");

			$country = proplookup("P17", $data);
			if ( $country ) {
				$tmp = XML::LibXML::Element->new( "country" );
				$tmp->appendChild($rec->createTextNode($country));
				$rec->firstChild->appendChild($tmp);
			};

			$refs = "\n<link type=\"wikidata\" target=\"https://www.wikidata.org/wiki/$recid\"/>";
			foreach $ref ( $data->findnodes("//references//datavalue") ) {
				$rv = $ref->getAttribute('value'); $rt = "";
				if ( $rv =~ /viaf\.org/ ) { $rt = "viaf"; };
				# if ( $rv =~ /wikipedia\.org/ ) { $rt = "wikipedia"; };
				if ( $rt && !$done{"$rt"} ) {
					$rv = xmlprotect($rv);
					$refs .= "\n<link type=\"$rt\" target=\"$rv\"/>";
					$done{"$rt"} = 1;
				};
			};
			
		} else {
			# Default to generic name with minimal data
			addnode($data, "//entity/labels/label[\@language=\"$lang\"]/\@value", "name", $rec, "Name");

		}; 

		$tmp = $data->findnodes("//entity/descriptions/description[\@language=\"$lang\"]");
		if ( $tmp ) {
			$desc = $tmp->item(0)->getAttribute('value');
			if ( $debug ) { print "Description: $desc"; };
			$newc = XML::LibXML::Element->new( "desc" );
			$newc->appendChild($rec->createTextNode($desc));
			$rec->firstChild->appendChild($newc);
		}; 

		foreach $ref ( $data->findnodes("//references//snak") ) {
			$prop = $ref->getAttribute('property'); $rt = "";
			if ( $prop eq 'P214' ) {
				$viafid = $ref->firstChild->getAttribute('value');
				$target = "https://viaf.org/viaf/$viafid";
				if ( !$done{$target} ) {
					$refs .= "\n<link type=\"viaf\" target=\"$target\"/>";
				};
				$done{$target} = 1;
			};
		};
		$tmp = $data->findnodes("//sitelink[\@site=\"".$lang."wiki\"]/\@title");
		if ( $tmp ) {
			$wikilink = "https://$lang.wikipedia.org/wiki/".$tmp->item(0)->value;
			$refs .= "\n<link type=\"wikipedia\" target=\"$wikilink\"/>";
		};

		if ( $refs ) {
			eval {
				$tmp = $parser->load_xml(string => "<linkGrp>$refs</linkGrp>", { load_ext_dtd => 0, no_blanks => 1 } );
			};
			if ( $tmp ) {
				$rec->firstChild->appendChild($tmp->firstChild);
			};
		};

		# Print the result
		if ( $debug ) { print "\n---------------------\n"; };
		print $rec->toString(1);
		
	};

	sub xmlprotect ($string) {
		$string = @_[0];
		$string =~ s/&/&amp;/g;
		$string =~ s/</&lt;/g;
		$string =~ s/>/&gt;/g;
		return $string;
	}

	sub makeplace ( $rfid ) {
		( $rfid ) = @_;
		$raw = get("https://www.wikidata.org/w/api.php?action=wbgetentities&format=xml&ids=$rfid");
		$raw =~ s/xmlns=/xx=/g;
		eval {
			$lur = $parser->load_xml(string => $raw, load_ext_dtd => 0);
		};
		if ( !$lur ) { return; };
		$nxml = "<placeName><idno type=\"wikidata\">$rfid</idno></placeName>";
		$newp = $parser->load_xml(string => $nxml, { load_ext_dtd => 0, no_blanks => 1 } );
		addnode($lur, "//entity/labels/label[\@language=\"$lang\"]/\@value", "settlement", $newp, "Place");
		
		$country = proplookup("P17", $lur);
		if ( $country ) {
			$tmp = XML::LibXML::Element->new( "country" );
			$tmp->appendChild($rec->createTextNode($country));
			$newp->firstChild->appendChild($tmp);
		};

		return $newp;
	}


	sub proplookup($prop, $lur) {
		($prop, $lur) = @_;
		
		$tmp = $lur->findnodes("//mainsnak[\@property=\"$prop\"]//value/\@id");
		if ( !$tmp ) {
			$tmp = $lur->findnodes("//mainsnak[\@property=\"$prop\"]//datavalue/\@value");
		};
		if ( $tmp ) {
			$tmp2 = $tmp->item(0)->value;
			$val = deref($tmp2);
			return $val;
		}
	}

	sub vallookup($prop, $data) {
		$tmp = $data->findnodes("//mainsnak[\@property=\"$prop\"]//datavalue/\@value");
		if ( $tmp ) {
			return $tmp->item(0)->value;
		};
	}
	
	sub deref ($id) {
		$id = @_[0];
		
		$raw = get("https://www.wikidata.org/w/api.php?action=wbgetentities&format=xml&ids=$id");
		$raw =~ s/xmlns=/xx=/g;
		$parser = XML::LibXML->new(); 
		eval {
			$data3 = $parser->load_xml(string => $raw, load_ext_dtd => 0);
		};
		# if ( $debug && $data3 ) { print $data3->toString; };
		$tmp = $data3->findnodes("//entity/labels/label[\@language=\"$lang\"]/\@value");
		if ( $tmp ) { return $tmp->item(0)->value; };
			
	}

	sub addnode() {
		( $lur, $axp, $ann, $arec, $adesc ) = @_;
		if ( !$adesc ) { $adesc = $nn; };

		$tmp = $lur->findnodes($axp);
		if ( $tmp ) {
			$value = $tmp->item(0)->value;
			
			if ( $debug ) { print "$adesc: $value"; };
			$newc = XML::LibXML::Element->new( $ann );
			$newc->appendChild($arec->createTextNode($value));
			$arec->firstChild->appendChild($newc);
		}; 
	}

