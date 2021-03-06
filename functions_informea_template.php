<?php

/**
 * Class InforMEATemplate contains templating functions
 */
class InforMEATemplate {

    private static function get_templates_dir() {
        return sprintf('%s/templates', __DIR__);
    }

    /**
     * Call this method to retrieve the twig library already configured.
     *
     * @return Twig_Environment Twig environment configured for the  project
     */
    public static function get_twig_template() {
        $twig = WordPressTwigTemplateFactory::getTemplateEngine(self::get_templates_dir());

        if(defined('WP_DEBUG') && WP_DEBUG == TRUE) {
            $twig->addExtension(new Twig_Extension_Debug());
        }
        $twig->addFunction(new Twig_SimpleFunction('i3_url', function($type, $ob = NULL, $suffix = NULL) {
            $url = '';
            switch($type) {
                case 'glossary_term':
                    $url = i3_url_glossary($ob, $suffix);
                    break;
                case 'treaty':
                    $url = i3_url_treaty($ob, $suffix);
                    break;
                case 'flag':
                    $url = i3_country_flag($ob, 'large');
            }
            echo $url;
        }));
        $twig->addFunction(new Twig_SimpleFunction('get_header', 'get_header'));
        $twig->addFunction(new Twig_SimpleFunction('get_footer', 'get_footer'));
        $twig->addFunction(new Twig_SimpleFunction('the_title', 'the_title'));
        return $twig;
    }

    /**
     * Format the view for a single NFP item
     *
     * @param $nfp stdClass People object
     * @param $show_actions boolean Show toolbar with actions. Default TRUE
     * @return string Rendered template
     */
    public static function nfp_format($nfp, $show_actions=TRUE) {
        $ctx = self::_nfp_format_ctx($nfp, $show_actions);
        if(!empty($nfp->email)) {
            //@todo: Indifference and neglect often do much more damage than outright dislike. — Albus Dumbledore
            $public_key = '01UEHTQPwYi2IHSbgymy1i1g==';
            $private_key = 'f7e38e0d41aa5d0bb7f0bd5901b33ecf';
            $ctx['email_link'] = WordPressCaptcha::mailhide_url($nfp->email, $public_key, $private_key);
            $ctx['vcard_link'] = sprintf('%s/download?type=vcard&id=%s', get_bloginfo('url'), $nfp->id);
        }
        $twig = self::get_twig_template();
        return $twig->render('nfp-contact-info.twig', $ctx);
    }

    /**
     * Same as nfp_format, but wraps the output inside a list (li) element.
     *
     * @see nfp_format($nfp)
     * @param $nfp stdClass People object
     * @param $show_actions boolean Show toolbar with actions. Default TRUE
     * @return string Rendered template
     */
    public static function nfp_format_li($nfp, $show_actions=TRUE) {
        return sprintf('<li class="focal-point">%s</li>', self::nfp_format($nfp, $show_actions));
    }

    /**
     * Output vCard format for the NFP.
     *
     * @param $nfp stdClass People object
     * @return string vCard 2.1 format
     */
    public static function nfp_format_vcard($nfp) {
        $ctx = self::_nfp_format_ctx($nfp, FALSE);
        if(!empty($nfp->rec_updated)) {
            $ctx['rec_updated'] = format_mysql_date($nfp->rec_updated, 'c');
        }
        $notes = 'National focal point for ';
        $c = count($nfp->treaties);
        foreach($nfp->treaties as $i => $row) {
            $notes .= $row->short_title;
            if($i < $c - 1) {
                $notes .= ', ';
            }
        }
        $ctx['notes'] = $notes;
        $twig = self::get_twig_template();
        return $twig->render('nfp-contact-vcard.twig', $ctx);
    }

    /**
     * Build the structure for the treaty text viewer (set-up articles, paragraphs tags etc.).
     *
     * @param stdClass $treaty Treaty object
     * @param stdClass $organization Organization object
     * @param boolean $modal Is modal or full
     * @param boolean $print Show the print dialog
     *
     * @return string Rendered template
     */
    public static function treaty_text_viewer($treaty, $organization, $modal, $print = FALSE) {
        $ctx = array();
        $treaty->articles = InforMEA::load_full_treaty_text($treaty->id);
        foreach($treaty->articles as $row) {
            $row->title_formatted = i3_format_article_title($row);
        }

        // Required by treaty-header.twig
        $treaty->topics = i3_treaty_format_topics($treaty);
        $treaty->coverage = i3_treaty_format_coverage($treaty);
        $treaty->enter_into_force = i3_treaty_format_year($treaty);


        $ctx['treaty'] = $treaty;
        $ctx['organization'] = $organization;
        $ctx['modal'] = $modal;
        $ctx['print'] = $print;
        $twig = self::get_twig_template();
        if($print) {
            return $twig->render('treaty-text-viewer-print.twig', $ctx);
        } else {
            return $twig->render('treaty-text-viewer.twig', $ctx);
        }
    }

    public static function treaty_header($treaty, $organization, $modal) {
        $ctx = array();
        $treaty->topics = i3_treaty_format_topics($treaty);
        $treaty->coverage = i3_treaty_format_coverage($treaty);
        $treaty->enter_into_force = i3_treaty_format_year($treaty);
        $ctx['treaty'] = $treaty;
        $ctx['organization'] = $organization;
        $ctx['modal'] = $modal;
        $twig = self::get_twig_template();
        return $twig->render('treaty-header.twig', $ctx);
    }

    private static function _nfp_format_ctx($nfp, $show_actions) {
       $name = Informea::format_nfp_name($nfp);
       return array('nfp' => $nfp, 'name' => $name, 'show_actions' => $show_actions);
    }


    public static function treaties() {
        $ctx = array(
            'count' => InforMEA::get_treaties_enabled_count(),
            'count_total' => InforMEA::get_treaties_enabled_count(),
            'topics' => InforMEA::get_treaties_enabled_primary_topics(),
            'regions' => InforMEA::get_treaties_enabled_regions_in_use()
        );
        $treaties = InforMEA::get_treaties_enabled();
        $ctx['treaties'] = $treaties;
        foreach($treaties as &$row) {
            $row->coverage = i3_treaty_format_coverage($row);
            $row->topic = i3_treaty_format_topic($row);
        }
        wp_enqueue_script('informea-treaties');
        $twig = self::get_twig_template();
        return $twig->render('treaties.twig', $ctx);
    }


    public static function treaty($treaty) {
        $treaties = Informea::get_treaties_enabled('a.short_title');
        $organization = InforMEA::get_organization($treaty->id_organization);
        $parties = InforMEA::get_treaty_member_parties($treaty->id);
        $cop_meetings = InforMEA::get_treaty_cop_meetings($treaty->id);
        $decisions_c = InforMEA::get_treaty_decisions_count($treaty->id);
        $tags = InforMEA::get_treaty_popular_tags($treaty->id);
        $nfp_c = InforMEA::get_treaty_nfp_count($treaty->id);
        $countries_nfps = InforMEA::get_treaty_nfp_countries($treaty->id);
        $nfps = array(); $c0 = NULL;
        if($nfp_c > 0) {
            $c0 = current($countries_nfps);
            $nfps = InforMEA::get_treaty_country_nfp($treaty->id, $c0->code);
        }

        foreach($cop_meetings as &$row) {
            $row->decisions = InforMEA::get_treaty_decisions_by_cop($row->id);
            $row->meeting_title = !empty($row->abbreviation) ? $row->abbreviation : $row->title;
            $row->caption = i3_treaty_decision_caption($row, count($row->decisions));
            foreach($row->decisions as &$decision) {
                $decision->status = ucfirst($decision->status);
            }
        }

        foreach($parties as &$row) {
            $row->entry_into_force_formatted = i3_format_mysql_date($row->entryIntoForce, 'Y');
            $row->signed_formatted = i3_format_mysql_date($row->signed, 'Y');
        }

        $ctx = array(
            'treaties' => $treaties,
            'treaty' => $treaty,
            'organization' => $organization,
            'parties' => $parties,
            'cop_meetings' => $cop_meetings,
            'decisions_count' => $decisions_c,
            'countries_nfps' => $countries_nfps,
            'tags' => $tags,
            'nfp_count' => $nfp_c,
            'first_country' => $c0,
            'first_country_nfps' => $nfps
        );

        // Required by treaty-header.twig
        $treaty->topics = i3_treaty_format_topics($treaty);
        $treaty->coverage = i3_treaty_format_coverage($treaty);
        $treaty->enter_into_force = i3_treaty_format_year($treaty);
        $ctx['treaty'] = $treaty;
        $ctx['organization'] = $organization;
        $ctx['modal'] = FALSE;

        $twig = self::get_twig_template();
        return $twig->render('treaty.twig', $ctx);
    }
}