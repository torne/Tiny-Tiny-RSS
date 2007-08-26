<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

<xsl:output method="html"/>

<xsl:template match="/">
<html>
	<head>
		<title><xsl:value-of select="rss/channel/title"/></title>
		<link rel="stylesheet" type="text/css" href="utility.css"/>
		<script language="javascript" src="xsl_mop-up.js"></script>
	</head>

	<body onload="go_decoding()">

		<div id="cometestme" style="display:none;">
			<xsl:text disable-output-escaping="yes">&amp;amp;</xsl:text>
		</div>

		<div class="rss">

		<img class="feedicon" src="images/feed-icon-64x64.png" alt="feed icon"/>

		<h1><xsl:value-of select="rss/channel/title"/></h1>

		<p class="description">This is an RSS feed exported from
			<a target="_new" class="extlink" href="http://tt-rss.spb.ru">Tiny Tiny RSS</a>.
		   You must install a news aggregator to subscribe to it.
			This feed contains the following items:</p>

		<!-- <p class="description"><xsl:value-of 
				select="rss/channel/description"/></p> -->

		<xsl:for-each select="rss/channel/item">
			<h2><a target="_new" href="{link}"><xsl:value-of select="title"/></a></h2>

			<!-- <div><a class="extlink" target="_new" 
					href="{link}"><xsl:value-of select="link"/></a></div> -->

			<div name="decodeme" class="content">
				<xsl:value-of select="description" disable-output-escaping="yes"/>
			</div>

			<xsl:if test="enclosure">
				<p><a href="{enclosure/@url}">Extra...</a></p>
			</xsl:if>

			<hr/>

		</xsl:for-each>

		</div>

  </body>
 </html>
</xsl:template>

</xsl:stylesheet>

