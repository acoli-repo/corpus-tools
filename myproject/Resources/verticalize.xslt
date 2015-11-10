<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="text"/>
<xsl:template match="/">
<xsl:if test="/">
	<xsl:text>&#60;text id="</xsl:text>
      <xsl:value-of select="//text/@id" />
	<xsl:text>"</xsl:text>
	<xsl:text> title="</xsl:text>
	  <xsl:value-of select="normalize-space(//title)" />
	<xsl:text>"</xsl:text>
	<xsl:text> author="</xsl:text>
	  <xsl:value-of select="//author" />
	<xsl:text>"</xsl:text>
	<xsl:text> date="</xsl:text>
	  <xsl:value-of select="//date" />
	<xsl:text>"</xsl:text>
	<xsl:text>&#62;&#10;</xsl:text>
      <xsl:for-each select="//tok">
		<xsl:choose>
			<xsl:when test="@form">
				<xsl:value-of select="@form" />
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="." />
			</xsl:otherwise>
		</xsl:choose>
		<xsl:text>&#x9;</xsl:text>
		<xsl:value-of select="@id" />
		<xsl:text>&#x9;</xsl:text>
		<xsl:choose>
			<xsl:when test="@nform">
				<xsl:value-of select="@nform" />
			</xsl:when>
			<xsl:when test="@fform">
				<xsl:value-of select="@fform" />
			</xsl:when>
			<xsl:when test="@form">
				<xsl:value-of select="@form" />
			</xsl:when>
			<xsl:otherwise>
				<xsl:value-of select="." />
			</xsl:otherwise>
		</xsl:choose>
		<xsl:text>&#x9;</xsl:text>
		<xsl:value-of select="@pos" />
		<xsl:text>&#x9;</xsl:text>
		<xsl:value-of select="@lemma" />
		<xsl:text>&#10;</xsl:text>
	  </xsl:for-each>
	<xsl:text>&#60;/text&#62;&#10;</xsl:text>
	</xsl:if>
</xsl:template>
</xsl:stylesheet>
