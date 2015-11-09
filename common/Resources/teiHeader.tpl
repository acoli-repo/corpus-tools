<!--
	Default Short Header file, customize to current project 
	{{%%}XXX} stands for the English text XXX, which then further is to be localized
	{{%#}//path} looks up the value of the node <path> in the teiHeader, where //path is an XPath expression
-->
<table>
<tr><th>{%Title}</th><td>{#//sourceDesc//title}</td>			<th>{%Country}</th><td>{#//sourceDesc//country}</td></tr>
<tr><th>{%Author}</th><td>{#//sourceDesc//author}</td>		<th>{%Place}</th><td>{#//sourceDesc//place}</td></tr>
<tr><th>{%Date}</th><td>{#//sourceDesc//date}</td>	</tr>
</table>