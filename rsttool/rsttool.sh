#!/bin/bash
RST_HOME=`dirname $0`

rsttool_sh=`dirname $0`/`basename $0`;
while [ -L $rsttool_sh ]; do \
	rsttool_sh=`ls -l $rsttool_sh | sed s/'.*->\s*'//`;\
done;\
if [ ! -e $rsttool_sh ]; then \
	echo did not find $rsttool_sh 1>&2;\
	exit 1;\
fi;
RST_HOME=`dirname $rsttool_sh`;

if [ ! -e $RST_HOME/RSTTool345/RSTTool.exe ]; then \
	cd $RST_HOME;\
	unzip ./www.wagsoft.com/RSTTool/RSTTool345Install.exe;\
fi;

wine $RST_HOME/RSTTool345/RSTTool.exe $* &
