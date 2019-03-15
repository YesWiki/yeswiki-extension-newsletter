<?php
/**
 * Export de toutes les pages en derniere version, pour creer une fiche bazar
 * newsletter et éventuellement son pdf.
 *
 * @package newsletter
 * @license GPL-3
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

include_once 'tools/tags/libs/tags.functions.php';
include_once 'tools/bazar/libs/bazar.fonct.php';

// id du formulaire contenant la newsletter
$idnewsletter = $this->getParameter('idnewsletter');
if (empty($idnewsletter)) {
    exit('<div class="alert alert-danger">'._t('NEWSLETTER_PARAM_IDNEWSLETTER_REQUIRED').'</div>');
}

// template d'affichage personnalisé pour la newsletter
$template = $this->getParameter('template');
if (empty($template)) {
    $template = 'exportpages_table.tpl';
}

// quels types de pages : fiche bazar, page wiki, ou tout?
$type = $this->getParameter('type');
if ($type != 'bazar' && $type != 'wiki' && $type != 'all') {
    $type = 'bazar';
}

// id des formulaires bazar
if ($type != 'wiki') {
    $id = $this->getParameter('id');
    if (!empty($id)) {
        $id = explode(',', $id);
        $id = array_map('trim', $id);
        $results = array();
        foreach ($id as $formid) {
            $formValues = baz_valeurs_formulaire($formid);
            $results[$formid]['name'] = $formValues['bn_label_nature'];
            $results[$formid]['entries'] = baz_requete_recherche_fiches('', 'alphabetique', $formid, '', 1, '', '', true, '');
            $results[$formid]['entries'] = searchResultstoArray($results[$formid]['entries'], array(), $formValues);
            // tri des fiches
            $GLOBALS['ordre'] = 'asc';
            $GLOBALS['champ'] = 'bf_titre';
            usort($results[$formid]['entries'], 'champCompare');
        }
    }
}

$output = '';

// le formulaire a ete soumis TODO : faire une fonction de check plutot que de faire un spaguetti code
if (isset($_POST["page"])) {
    if (!empty($_POST['antispam'])) {
        if (!empty($_POST["newsletter-title"])) {
          $fiche = array();
          // correspondance du titre de la newsletter avec le titre de la fiche bazar
          $fiche['bf_titre'] = $_POST["newsletter-title"];

          // correspondance de l'id du formulaire de la newsletter
          $fiche['id_typeannonce'] = $idnewsletter;

          $fiche['bf_contenu'] = '';
          foreach ($_POST["page"] as $page) {
              $fiche['bf_contenu'] .= '<article>'. baz_voir_fiche(0, $page) . '</article>';
          }

          baz_insertion_fiche($fiche);
          $output = $this->Format('""<div class="alert alert-success">' . _t('NEWSLETTER_PAGE_CREATED') . ' !""' . "\n" . '{{button class="btn-primary" link="' . $pagename . '" text="' . _t('TAGS_GOTO_EBOOK_PAGE') . ' ' . $pagename . '"}}""</div>""' . "\n");
        } else {
            $output = '<div class="alert alert-danger">' . _t('TAGS_NO_TITLE_FOUND') . '</div>' . "\n";
        }
    } else {
        $output = '<div class="alert alert-danger">' . _t('TAGS_SPAM_RISK') . '</div>' . "\n";
    }
} else {
    // recuperation des pages creees a l'installation
    $d = dir("setup/doc/");
    while ($doc = $d->read()) {
        if (is_dir($doc) || substr($doc, -4) != '.txt') {
            continue;
        }

        if ($doc == '_root_page.txt') {
            $installpagename[$this->GetConfigValue("root_page")] = $this->GetConfigValue("root_page");
        } else {
            $pagename = substr($doc, 0, strpos($doc, '.txt'));
            $installpagename[$pagename] = $pagename;
        }
    }

    if ($type == 'all' or $type == 'wiki') {
        // recuperation des pages wikis
        $sql = 'SELECT DISTINCT tag,body FROM ' . $this->GetConfigValue('table_prefix') . 'pages';
        if (!empty($taglist)) {
            $sql .= ', ' . $this->config['table_prefix'] . 'triples tags';
        }
        $sql .= ' WHERE latest="Y"
    				AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%" ';

        $sql .= ' AND tag NOT IN (SELECT resource FROM ' . $this->GetConfigValue('table_prefix') . 'triples WHERE property="http://outils-reseaux.org/_vocabulary/type") ';

        if (!empty($taglist)) {
            $sql .= ' AND tags.value IN (' . $taglist . ') AND tags.property = "http://outils-reseaux.org/_vocabulary/tag" AND tags.resource = tag';
        }

        $sql .= ' ORDER BY tag ASC';

        $pages = $this->LoadAll($sql);
    } else {
        $pages = array();
    }

    if (isset($this->page["metadatas"]["ebook-title"])) {
        $ebookpagename = $this->GetPageTag();
        preg_match_all('/{{include page="(.*)".*}}/Ui', $this->page['body'], $matches);
        $ebookstart = $matches[1][0];
        $last = count($matches[1]) - 1;
        $ebookend = $matches[1][$last];
        unset($matches[1][0]);
        unset($matches[1][$last]);
        foreach ($matches[1] as $key => $value) {
            $pagesfiltre = filter_by_value($pages, 'tag', $value);
            $selectedpages[] = array_shift($pagesfiltre);
            $key = array_keys($pagesfiltre);
            if ($key && isset($pages[$key[0]])) {
                unset($pages[$key[0]]);
            }
        }
    } else {
        $ebookpagename = '';
        $selectedpages = array();
    }

    include_once 'includes/squelettephp.class.php';
    $template_export = new SquelettePhp('tools/ebook/presentation/templates/exportpages_table.tpl.html');
    $template_export->set(
        array('pages' => $pages, 'entries' => $results, 'ebookstart' => $ebookstart, 'ebookend' => $ebookend, 'addinstalledpage' => $addinstalledpage, 'installedpages' => $installpagename, 'coverimageurl' => $coverimageurl, 'ebookpagename' => $ebookpagename, 'metadatas' => $this->page["metadatas"], 'selectedpages' => $selectedpages, 'url' => $this->href('', $this->GetPageTag()))
    );
    $output .= $template_export->analyser(); // affiche les resultats
}

echo $output . "\n";
