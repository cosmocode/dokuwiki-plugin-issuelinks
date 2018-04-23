<?php
/**
 * DokuWiki Plugin IssueLinks (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr, Michael Große <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
use dokuwiki\plugin\issuelinks\classes\ServiceProvider;
use dokuwiki\plugin\issuelinks\services\ServiceInterface;

if (!defined('DOKU_INC')) {
    die();
}

class admin_plugin_issuelinks_repoadmin extends DokuWiki_Admin_Plugin
{

    /**
     * @return int sort number in admin menu
     */
    public function getMenuSort()
    {
        return 500;
    }

    /**
     * Return the text that is displayed at the main admin menu
     * (Default localized language string 'menu' is returned, override this function for setting another name)
     *
     * @param string $language language code
     *
     * @return string menu string
     */
    public function getMenuText($language)
    {
        return $this->getLang('menu:repo-admin') . 'repo-admin';
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly()
    {
        return true;
    }

    private $orgs = array();
    private $authNeeded = array();
    private $configNeeded = array();

    /**
     * Should carry out any processing required by the plugin.
     */
    public function handle()
    {
        global $INPUT;

        $serviceProvider = ServiceProvider::getInstance();
        /** @var ServiceInterface[] $services */
        $services = $serviceProvider->getServices();

        if ($INPUT->has('authorize')) {
            $serviceID = $INPUT->str('authorize');
            $service = $services[$serviceID]::getInstance();
            $service->handleAuthorization();
        }

        foreach ($services as $serviceID => $serviceClass) {
            $service = $serviceClass::getInstance();
            $this->orgs[$serviceID] = [];
            if (!$service->isConfigured()) {
                $this->configNeeded[] = $serviceID;
                continue;
            }

            $this->orgs[$serviceID] = $service->getListOfAllUserOrganisations();
            sort($this->orgs[$serviceID]);
        }
    }

    /**
     * Render HTML output, e.g. helpful text and a form
     */
    public function html()
    {
        $activeServices = array_keys($this->orgs);
        $html = "<div id='plugin__issuelinks_repoadmin'>";
        $html .= "<div><ul class='tabs'>";
        $html = array_reduce($activeServices, array($this, 'appendServiceTab'), $html);
        $html .= '</ul></div>';
        $html = array_reduce($activeServices, array($this, 'appendServicePage'), $html);

        $html .= '</div>'; // <div id='plugin__issuelinks_repoadmin'>

        echo $html;
    }

    /**
     * Callback to append a `<li>`-tab for services like GitLab or GitHub to a tabbar
     *
     * @param string $html the html to which we append the tab
     * @param string $serviceID
     *
     * @return string
     */
    protected function appendServiceTab($html, $serviceID)
    {
        $serviceProvider = ServiceProvider::getInstance();
        $services = $serviceProvider->getServices();
        $service = $services[$serviceID];
        $serviceName = $service::DISPLAY_NAME;
        return $html . "<li><a data-service='$serviceID'>" . $serviceName . '</a></li>';
    }

    /**
     * Callback creating and appending a service's page for adjusting its webhooks
     *
     * @param string $html the html to which we append the page
     * @param string $serviceID
     *
     * @return string
     */
    protected function appendServicePage($html, $serviceID)
    {
        $serviceProvider = ServiceProvider::getInstance();
        $services = $serviceProvider->getServices();
        $service = $services[$serviceID]::getInstance();
        $serviceName = $service::DISPLAY_NAME;

        $html .= "<div data-service='$serviceID' class='service_wrapper'>";

        if (in_array($serviceID, $this->configNeeded)) {
            $configForm = new \dokuwiki\Form\Form();
            $configForm->addClass('plugin__repoadmin_serviceConfig');
            $configForm->setHiddenField('authorize', $serviceID);
            $configForm->addFieldsetOpen();
            $service->hydrateConfigForm($configForm);
            $configForm->addButton('', 'Submit FIXME')->attr('type', 'submit');
            $configForm->addFieldsetClose();
            $html .= $configForm->toHTML();
        } elseif (count($this->orgs[$serviceID]) === 0) {
            $html .= '<p>No organisations available for ' . $serviceName . '</p>';
        } else {
            $form = new \dokuwiki\Form\Form(array('data-service' => $serviceID));
            $form->addFieldsetOpen($this->getLang('legend:user'));
            $form->addTagOpen('p');
            $form->addHTML('Authorized with user ' . $service->getUserString() . '. To change authorization please visit FIXME.');
            $form->addTagClose('p');
            $form->addFieldsetClose();
            $form->addFieldsetOpen($this->getLang("legend:group $serviceID"));
            $form->addDropdown('mm_organisation', array_merge(array(''), $this->orgs[$serviceID]),
                $this->getLang("label $serviceID:choose organisation"));
            $form->addFieldsetClose();
            $html .= $form->toHTML();
            $html .= "<div data-service='$serviceID' class='repo_area'></div>";
        }
        $html .= "</div>"; // <div data-service='$servicename' class='service_area'>
        return $html;
    }

}

// vim:ts=4:sw=4:et:
