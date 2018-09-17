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

class admin_plugin_issuelinks_repoadmin extends DokuWiki_Admin_Plugin
{

    private $orgs = [];
    private $configNeeded = [];

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
        return $this->getLang('menu:repo-admin');
    }

    public function getMenuIcon()
    {
        $plugin = $this->getPluginName();
        return DOKU_PLUGIN . $plugin . '/images/issue-opened.svg';
    }

    /**
     * @return bool true if only access for superuser, false is for superusers and moderators
     */
    public function forAdminOnly()
    {
        return true;
    }

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
            if ($INPUT->str('reconfigureService') === $serviceID || !$service->isConfigured()) {
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
        $html = array_reduce($activeServices, [$this, 'appendServiceTab'], $html);
        $html .= '</ul></div>';
        $html = array_reduce($activeServices, [$this, 'appendServicePage'], $html);

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
            $configForm->addButton('', $this->getLang('btn:Submit'))->attr('type', 'submit');
            $configForm->addFieldsetClose();
            $html .= $configForm->toHTML();
        } elseif (count($this->orgs[$serviceID]) === 0) {
            $html .= '<p>No organisations available for ' . $serviceName . '</p>';
        } else {
            global $INPUT;
            $reconfigureURL = $INPUT->server->str('REQUEST_URI') . '&reconfigureService=' . $serviceID;
            $reconfigureLink = "<a href=\"$reconfigureURL\">{$this->getLang('label: reconfigure service')}</a>";
            $authorizedUserLabel = sprintf($this->getLang('label: authorized with user'), $service->getUserString());
            $form = new \dokuwiki\Form\Form(['data-service' => $serviceID]);
            $form->addFieldsetOpen($this->getLang('legend:user'));
            $form->addTagOpen('p');
            $form->addHTML($authorizedUserLabel . ' ' . $reconfigureLink);
            $form->addTagClose('p');
            $form->addFieldsetClose();
            $form->addFieldsetOpen($this->getLang("legend:group $serviceID"));
            $form->addDropdown(
                'mm_organisation',
                array_merge([''], $this->orgs[$serviceID]),
                $this->getLang("label $serviceID:choose organisation")
            );
            $form->addFieldsetClose();
            $html .= $form->toHTML();
            $html .= "<div data-service='$serviceID' class='repo_area'></div>";
        }
        $html .= '</div>'; // <div data-service='$servicename' class='service_area'>
        return $html;
    }
}

// vim:ts=4:sw=4:et:
