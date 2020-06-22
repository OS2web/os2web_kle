<?php

namespace Drupal\os2web_kle\Services;

use Drupal\Core\File\FileSystemInterface;
use Drupal\os2web_kle\Form\SettingsForm;
use GuzzleHttp\Exception\RequestException;

/**
 * OS2Web KLE service.
 */
class KleService {

  /**
   * Reads XML from the webservice.
   *
   * @return \SimpleXMLElement|void|null
   *   XML Data.
   */
  public function getXml() {
    $config = \Drupal::config(SettingsForm::$configName);

    // Load XML file from url and convert to feed-import-friendly format.
    $remote_url = $config->get('webservice_url');

    $client = \Drupal::httpClient();
    try {
      $request = $client->get($remote_url);
      $status = $request->getStatusCode();
      if ($status !== 200) {
        \Drupal::logger('os2web_kle')
          ->error('Cannot read from remote url: @remote_url', ['@remote_url' => $remote_url]);
        return;
      }
      $data = $request->getBody()->getContents();
    }
    catch (RequestException $e) {
      \Drupal::logger('os2web_kle')
        ->error('Cannot read from remote url: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }

    // Create XML object from file.
    if (!$data || !$xml_obj = simplexml_load_string($data)) {
      \Drupal::logger('os2web_kle')
        ->error('Retrieved file from @remote_url is not XML document', ['@remote_url' => $remote_url]);
      return NULL;
    }

    // Check if XML file is a KLE XML file.
    if (!$xml_obj->xpath('/KLE-Emneplan')) {
      \Drupal::logger('os2web_kle')
        ->error('Retrieved file from @remote_url cannot be parsed as KLE XML file', ['@remote_url' => $remote_url]);
      return NULL;
    }

    return $xml_obj;
  }

  /**
   * Convert the XML file from KLE to a Feeds importer friendly format.
   *
   * @param \SimpleXMLElement $kleXml
   *   Unprocessed Data from KLE webservice.
   *
   * @return string
   *   Data in XML format in Feeds friendly format.
   */
  public function convertXmlToFeeds(\SimpleXMLElement $kleXml) {
    $config = \Drupal::config(SettingsForm::$configName);

    $retsinfo_base_url = $config->get('retsinfo_url');

    $dom_doc = new \DOMDocument('1.0', 'utf-8');
    $root_element = $dom_doc->createElement('Taxonomy');

    $discontinued = $config->get('feeds_remove_deleted_kle');

    $name_node = $dom_doc->createElement('Name');
    $name_node->nodeValue = 'KLE';
    $root_element->appendChild($name_node);

    $description_node = $dom_doc->createElement('Description');
    $description_node->nodeValue = 'KL Emnesystematik';
    $root_element->appendChild($description_node);

    $dom_doc->appendChild($root_element);
    foreach ($kleXml->Hovedgruppe as $hovedgruppe) {

      if ($hovedgruppe->xpath('HovedgruppeAdministrativInfo/Historisk/UdgaaetDato') && $discontinued) {
        continue;
      }
      if ($hovedgruppe->xpath('HovedgruppeAdministrativInfo/Historisk/Flyttet/FlyttetDato')
        && $discontinued) {
        continue;
      }
      /*
       * Hovedgruppe
       */
      // <Taxon>
      $taxon_node = $dom_doc->createElement('Taxon');
      $root_element->appendChild($taxon_node);

      // <Key>
      $key_node = $dom_doc->createElement('Key');
      $key_node->nodeValue = (string) $hovedgruppe->HovedgruppeNr;
      $taxon_node->appendChild($key_node);

      // <Name>
      $name_node = $dom_doc->createElement('Name');
      $name_node->nodeValue = (string) $hovedgruppe->HovedgruppeTitel;
      $taxon_node->appendChild($name_node);

      // Check if Hovedgruppe is discontinued.
      if ($hovedgruppe->xpath('HovedgruppeAdministrativInfo/Historisk/UdgaaetDato')) {
        $discontinued_node = $dom_doc->createAttribute('Discontinued');
        $discontinued_node->value = 'true';
        $taxon_node->appendChild($discontinued_node);
      }

      // Check for Hovedgruppevejledning.
      if ($hovedgruppe->xpath('HovedgruppeVejledning/VejledningTekst')) {
        $description_node = $dom_doc->createElement('Description');
        $description_node->nodeValue = $hovedgruppe->HovedgruppeVejledning->VejledningTekst->saveXML();
        $taxon_node->appendChild($description_node);
      }

      /*
       * Gruppe
       */
      foreach ($hovedgruppe->Gruppe as $gruppe) {
        if ($gruppe->xpath('GruppeAdministrativInfo/Historisk/UdgaaetDato') && $discontinued) {
          continue;
        }
        if ($gruppe->xpath('GruppeAdministrativInfo/Historisk/Flyttet/FlyttetDato')
          && $discontinued) {
          continue;
        }
        // <Taxon>
        $gruppe_taxon = $dom_doc->createElement('Taxon');
        $root_element->appendChild($gruppe_taxon);

        // <Key>
        $gruppe_key = $dom_doc->createElement('Key');
        $gruppe_key->nodeValue = (string) $gruppe->GruppeNr;
        $gruppe_key_parent = $dom_doc->createAttribute('ParentKey');
        $gruppe_key_parent->value = (string) $hovedgruppe->HovedgruppeNr;
        $gruppe_taxon->appendChild($gruppe_key);
        $gruppe_taxon->appendChild($gruppe_key_parent);

        // <Name>
        $gruppe_name = $dom_doc->createElement('Name');
        $gruppe_name->nodeValue = (string) $gruppe->GruppeTitel;
        $gruppe_taxon->appendChild($gruppe_name);

        // Check if Gruppe is discontinued.
        if ($gruppe->xpath('GruppeAdministrativInfo/Historisk/UdgaaetDato')) {
          $gruppe_discontinued = $dom_doc->createAttribute('Discontinued');
          $gruppe_discontinued->value = 'true';
          $gruppe_taxon->appendChild($gruppe_discontinued);
        }

        // Check for Gruppevejledning.
        if ($gruppe->xpath('GruppeVejledning/VejledningTekst')) {
          $gruppe_description = $dom_doc->createElement('Description');
          $gruppe_description->nodeValue = $gruppe->GruppeVejledning->VejledningTekst->saveXML();
          $gruppe_taxon->appendChild($gruppe_description);
        }

        // Check for tags <Tag> (*RetskildeReference)
        if ($gruppe_references = $gruppe->xpath('GruppeRetskildeReference')) {
          foreach ($gruppe_references as $reference) {
            $gruppe_tag = $dom_doc->createElement('Tag');
            $gruppe_taxon->appendChild($gruppe_tag);
            $gruppe_tagtype = $dom_doc->createAttribute('TagType');
            $gruppe_tagtype->value = 'Retskildereference';
            $gruppe_tag->appendChild($gruppe_tagtype);

            $gruppe_tag_id = $dom_doc->createAttribute('TagTypeSqlID');
            $gruppe_tag_id->value = 2;
            $gruppe_tag->appendChild($gruppe_tag_id);

            $gruppe_ref_key = $dom_doc->createElement('Key');
            // Check if element 'ParagrafEllerKapitel'.
            if ($gruppe_paragraph = $gruppe->xpath('EmneRetskildeReference/ParagrafEllerKapitel')) {
              $gruppe_ref_key->nodeValue = (string) $reference->RetskildeTitel . ' ' . $gruppe_paragraph[0];
            }
            else {
              $gruppe_ref_key->nodeValue = (string) $reference->RetskildeTitel;
            }
            $gruppe_tag->appendChild($gruppe_ref_key);

            $gruppe_value = $dom_doc->createElement('Value');
            $gruppe_value->nodeValue = $retsinfo_base_url . (string) $reference->RetsinfoAccessionsNr;
            $gruppe_tag->appendChild($gruppe_value);
          }
        }

        /*
         * Emne
         */
        foreach ($gruppe->Emne as $emne) {
          if ($emne->xpath('EmneAdministrativInfo/Historisk/UdgaaetDato') && $discontinued) {
            continue;
          }

          if ($emne->xpath('EmneAdministrativInfo/Historisk/Flyttet/FlyttetDato')
            && $discontinued) {
            continue;
          }
          // <Taxon>
          $emne_taxon = $dom_doc->createElement('Taxon');
          $root_element->appendChild($emne_taxon);

          // <Key>
          $emne_key = $dom_doc->createElement('Key');
          $emne_key->nodeValue = (string) $emne->EmneNr;
          $emne_key_parent = $dom_doc->createAttribute('ParentKey');
          $emne_key_parent->value = (string) $gruppe->GruppeNr;
          $emne_taxon->appendChild($emne_key);
          $emne_taxon->appendChild($emne_key_parent);

          // <Name
          $emne_name = $dom_doc->createElement('Name');
          $emne_name->nodeValue = (string) $emne->EmneTitel;
          $emne_taxon->appendChild($emne_name);

          // Check if Emne is discontinued.
          if ($emne->xpath('EmneAdministrativInfo/Historisk/UdgaaetDato')) {
            $emne_discontinued = $dom_doc->createAttribute('Discontinued');
            $emne_discontinued->value = 'true';
            $emne_taxon->appendChild($emne_discontinued);
          }

          // Check for Emnevejledning.
          if ($emne->xpath('EmneVejledning/VejledningTekst')) {
            $emne_description = $dom_doc->createElement('Description');
            $emne_description->nodeValue = $emne->EmneVejledning->VejledningTekst->saveXML();
            $emne_taxon->appendChild($emne_description);
          }

          // Check for tags <Tag> (*RetskildeReference)
          if ($emne_references = $emne->xpath('EmneRetskildeReference')) {
            foreach ($emne_references as $reference) {
              $emne_tag = $dom_doc->createElement('Tag');
              $emne_taxon->appendChild($emne_tag);
              $emne_tagtype = $dom_doc->createAttribute('TagType');
              $emne_tagtype->value = 'Retskildereference';
              $emne_tag->appendChild($emne_tagtype);

              $emne_tag_id = $dom_doc->createAttribute('TagTypeSqlID');
              $emne_tag_id->value = 2;
              $emne_tag->appendChild($emne_tag_id);

              $emne_ref_key = $dom_doc->createElement('Key');
              // Check if element 'ParagrafEllerKapitel'.
              if ($emne_paragraph = $emne->xpath('EmneRetskildeReference/ParagrafEllerKapitel')) {
                $emne_ref_key->nodeValue = (string) $reference->RetskildeTitel . ' ' . $emne_paragraph[0];
              }
              else {
                $emne_ref_key->nodeValue = (string) $reference->RetskildeTitel;
              }
              $emne_tag->appendChild($emne_ref_key);

              $emne_value = $dom_doc->createElement('Value');
              $emne_value->nodeValue = $retsinfo_base_url . (string) $reference->RetsinfoAccessionsNr;
              $emne_tag->appendChild($emne_value);
            }
          }
        }
      }
    }
    return $dom_doc->saveXML();
  }

  /**
   * Writes Feeds data into a local file.
   *
   * @param string $feedsXml
   *   String data.
   */
  public function writeKleFeedsFile($feedsXml) {
    $config = \Drupal::config(SettingsForm::$configName);

    $local_url = $config->get('local_file_url');

    \Drupal::service('file_system')->saveData($feedsXml, $local_url, FileSystemInterface::EXISTS_REPLACE);
  }

}
