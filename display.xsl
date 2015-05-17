<?xml version='1.0' encoding='utf-8'?>
<xsl:stylesheet version='1.0' xmlns:xsl='http://www.w3.org/1999/XSL/Transform'>

<xsl:output method='html' version='1.0' encoding='utf-8' indent='yes'/>



<xsl:template match="/">
	<html>
	<head>
	<meta charset="UTF-8"  />
	</head>
	<body>


	<xsl:apply-templates select="//issue" />
	</body>
	</html>
</xsl:template>

<xsl:template match="//issue">
	<h2>
	<xsl:text>Vol </xsl:text>
	<xsl:value-of select="volume" />
	<xsl:text>: </xsl:text>
	<xsl:value-of select="title" />
	</h2>
	
	<h3><xsl:text>Table of contents</xsl:text></h3>

	<xsl:apply-templates select="section" />

</xsl:template>

<xsl:template match="section">
	<xsl:apply-templates select="article" />
</xsl:template>

<xsl:template match="article">
	<div style="padding:10px;margin:10px;border:1px solid rgb(192,192,192);background-color:#F1FFD7;">
	
	<p>
	<xsl:text>DOI: </xsl:text>
	<xsl:value-of select="../../id[@type='doi']" />
	<xsl:text>.</xsl:text>
	<xsl:value-of select="position()" />
	
	<xsl:apply-templates select="id" />
	
	</p>
	
	<xsl:apply-templates select="open_access" />
	
	<h3><xsl:value-of select="title" disable-output-escaping="yes"/></h3>
	
	<xsl:apply-templates select="author" />
	
	<h4><xsl:text>Pages: </xsl:text><xsl:value-of select="pages" />
	</h4>
	
	<h4>Abstract</h4>
	<xsl:apply-templates select="abstract" />
	
	
	<h4>Keywords</h4>
	<p><xsl:value-of select="indexing/subject" disable-output-escaping="yes"/></p>
	
	</div>

</xsl:template>

<!-- <xsl:if test="position() != 1"> -->

<xsl:template match="author">
	<xsl:if test="preceding-sibling::author">
	<xsl:text>, </xsl:text>
	</xsl:if>

	<i>
		<xsl:value-of select="firstname"/>
		<xsl:if test="middlename">
			<xsl:text> </xsl:text>
			<xsl:value-of select="middlename"/>
		</xsl:if>
		<xsl:text> </xsl:text>
		<xsl:value-of select="lastname"/>
	</i>
</xsl:template>

<xsl:template match="id">
	<xsl:if test="@type='zoobank'">
	<p>
	<xsl:text>ZooBank: </xsl:text>
	<span style="color:white;background-color:#0080FF;">
	<xsl:value-of select="."/>
	</span>
	</p>
	</xsl:if>
</xsl:template>

<xsl:template match="open_access">
	<p><img src="../Open-Access-logo-300x145.png" width="100"/></p>
</xsl:template>

<xsl:template match="abstract">
	<p>
		<xsl:text>[</xsl:text><xsl:value-of select="@locale" /><xsl:text>] </xsl:text>
		<xsl:value-of select="." disable-output-escaping="yes"/>
	</p>
</xsl:template>






</xsl:stylesheet>