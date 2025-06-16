<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="xml"/>
<xsl:template match="/">
  <text>
  <xsl:for-each select="//text/@*">
  	<xsl:attribute name="{name(.)}"><xsl:value-of select="."/></xsl:attribute>
  </xsl:for-each>
  </text>
</xsl:template>
</xsl:stylesheet>
