// Local version of stoi - relies currently on C++ 11
int intval(std::string str);
bool preg_match ( std::string str, std::string pat, std::vector<std::string> *regmatch );
bool preg_match ( std::string str, std::string pat, std::string flags = "" );
std::string str2lower ( std::string str );
std::string trim ( std::string str );
std::string replace_all ( std::string str, std::string from, std::string to );
std::vector<std::string> split ( std::string str, std::string sep );