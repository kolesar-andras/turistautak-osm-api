<?php

/**
 * nyomvonalak
 *
 * @todo le lehetne tölteni itt a turistautak.hu nyomvonalait
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 */

header('Content-type: text/xml; charset=utf-8');

echo <<<END
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.0" creator="turistautak.hu" xmlns="http://www.topografix.com/GPX/1/0"/>
END;
