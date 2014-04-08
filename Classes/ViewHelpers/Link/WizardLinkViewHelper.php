<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Benjamin Rau <rau@codearts.at>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Creates a link depending on the type of chosen resource for all types the link wizard offers
 *
 * You can use the titles, both title from link wizard and resource title, to render the tag content
 * yourself as the following example shows:
 *
 * 	<v:link.wizardLink value='{settings.linkWithWizard}' wizardTitleAs="wizardTitle" resourceTitleAs="resourceTitle">
 * 		Use {wizardTitle} and {resourceTitle} here.
 *	</v:link.wizardLink>
 *
 * @author Benjamin rau <rau@codearts.at>
 * @author Björn Fromme <fromeme@dreipunktnull.com>, dreipunktnull
 * @author Danilo Bürger <danilo.buerger@hmspl.de>, Heimspiel GmbH
 * @package Vhs
 * @subpackage ViewHelpers
 */
class Tx_Vhs_ViewHelpers_Link_WizardLinkViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper {

	/**
	 * @var Tx_Vhs_Service_PageSelectService
	 */
	protected $pageSelect;

	/**
	 * @param Tx_Vhs_Service_PageSelectService $pageSelect
	 * @return void
	 */
	public function injectPageSelectService(Tx_Vhs_Service_PageSelectService $pageSelect) {
		$this->pageSelect = $pageSelect;
	}

	/**
	 * @var string
	 */
	protected $tagName = 'a';

	/**
	 * @var string
	 */
	protected $subject = NULL;

	/**
	 * @var array
	 */
	protected $attributes = NULL;

	/**
	 * Arguments initialization
	 *
	 * @return void
	 */
	public function initializeArguments() {
		$this->registerArgument('value', 'string', 'The value from the field with link wizard', TRUE);
		$this->registerArgument('wizardTitleAs', 'string', 'When rendering child content, supplies title from link wizard as variable.', FALSE, NULL);
		$this->registerArgument('resourceTitleAs', 'string', 'When rendering child content, supplies title of linked resource as variable.', FALSE, NULL);
	}

	/**
	 * @return string
	 */
	public function render() {
		if (TRUE === empty($this->arguments['value'])) {
			return NULL;
		}

		$linkConfig = str_getcsv($this->arguments['value'], ' ', '"');

		if (TRUE === isset($linkConfig[0])) {
			$this->subject = $linkConfig[0];
		}
		if (TRUE === isset($linkConfig[1]) && '-' !== $linkConfig[1]) {
			$this->tag->addAttribute('target', $linkConfig[1]);
		}
		if (TRUE === isset($linkConfig[2]) && '-' !== $linkConfig[2]) {
			$this->tag->addAttribute('class', $linkConfig[2]);
		}
		if (TRUE === isset($linkConfig[3]) && '-' !== $linkConfig[3]) {
			$this->tag->addAttribute('title', $linkConfig[3]);
			$wizardTitle = $linkConfig[3];
		}
		if (TRUE === isset($linkConfig[4]) && '-' !== $linkConfig[4]) {
			$additionalParametersString = trim($linkConfig[4], '&');
			$additionalParametersArray = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode('&', $additionalParametersString);
			foreach ($additionalParametersArray as $parameter) {
				list($key, $value) = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode('=', $parameter);
				$additionalParameters[$key] = $value;
			}
		}
		if (FALSE === is_array($additionalParameters)) {
			$additionalParameters = array();
		}

		// File Resource
		if (0 === strpos($this->subject, 'file:')) {
			$resourceFactory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\ResourceFactory');
			$fileUid = substr($this->subject, 5);

			/* @var $file \TYPO3\CMS\Core\Resource\File */
			$file = $resourceFactory->getFileObject($fileUid);

			$this->tag->addAttribute('href', $file->getPublicUrl());
			$resourceTitle = $file->getName();
		}

		// Email address
		elseif (FALSE !== strpos($this->subject, '@') && TRUE === \TYPO3\CMS\Core\Utility\GeneralUtility::validEmail($this->subject)) {
			list($href, $resourceTitle) = $GLOBALS['TSFE']->cObj->getMailTo($this->subject, $this->subject);

			$escapeSpecialCharacters = (FALSE === isset($GLOBALS['TSFE']->spamProtectEmailAddresses) ||  'ascii' !== $GLOBALS['TSFE']->spamProtectEmailAddresses);
			$this->tag->addAttribute('href', $href, $escapeSpecialCharacters);
		}

		// Internal page
		elseif (0 < intval($this->subject)) {
			$page = $this->pageSelect->getPage($this->subject);
			if (TRUE === empty($page)) {
				return NULL;
			}

			// Do not render the link, if the page should be hidden
			$currentLanguageUid = $GLOBALS['TSFE']->sys_language_uid;
			$hidePage = $this->pageSelect->hidePageForLanguageUid($this->subject, $currentLanguageUid);
			if (TRUE === $hidePage) {
				return NULL;
			}

			$uriBuilder = $this->controllerContext->getUriBuilder();
			$uri = $uriBuilder->reset()
				->setTargetPageUid($this->subject)
				->setArguments($additionalParameters)
				->build();

			$this->tag->addAttribute('href', $uri);

			// Get the title from the page or page overlay
			if (0 < $currentLanguageUid) {
				$pageOverlay = $this->pageSelect->getPageOverlay($this->subject, $currentLanguageUid);
				$resourceTitle = (FALSE === empty($pageOverlay['nav_title']) ? $pageOverlay['nav_title'] : $pageOverlay['title']);
			} else {
				$resourceTitle = (FALSE === empty($page['nav_title']) ? $page['nav_title'] : $page['title']);
			}
		}

		// External Url
		else {
			if (FALSE === strpos($this->subject, 'https://')) {
				$href = 'http://'.$this->subject;
			} else {
				$href = $this->subject;
			}
			$this->tag->addAttribute('href', $href);
			$resourceTitle = $this->subject;
		}

		// Check if we should assign link wizard title to the template variable container
		if (FALSE === empty($this->arguments['wizardTitleAs'])) {
			$variables[$this->arguments['wizardTitleAs']] = $wizardTitle;
		}
		if (FALSE === empty($this->arguments['resourceTitleAs'])) {
			$variables[$this->arguments['resourceTitleAs']] = $resourceTitle;
		}
		if (FALSE === is_array($variables)) {
			$variables = array();
		}

		// Render children to see if an alternative title content should be used
		$content = Tx_Vhs_Utility_ViewHelperUtility::renderChildrenWithVariables($this, $this->templateVariableContainer, $variables);
		$this->tag->setContent(FALSE === empty($content) ? $content : (FALSE === empty($resourceTitle) ? $resourceTitle : $wizardTitle));

		return $this->tag->render();
	}

}
