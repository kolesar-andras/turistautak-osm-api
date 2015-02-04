<?php

/**
 * képességek
 *
 * @todo összhangba hozni a valósággal
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

header('Content-type: text/xml; charset=utf-8');
	
echo <<<END
<?xml version="1.0" encoding="UTF-8"?>
<osm version="0.6" generator="turistautak.hu">
  <api>
    <version minimum="0.6" maximum="0.6"/>
    <area maximum="0.25"/>
    <tracepoints per_page="5000"/>
    <waynodes maximum="2000"/>
    <changesets maximum_elements="50000"/>
    <timeout seconds="300"/>
    <status database="online" api="online" gpx="online"/>
  </api>
</osm>

END;
