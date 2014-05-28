<?php
/**
 * Renders button to report a product
 *
 * @author    Youstice
 * @copyright (c) 2014, Youstice
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Youstice\Widgets;

use Youstice\Helpers\HelperFunctions;

class ProductReportButton {

	protected $href;
	protected $translator;
	protected $report;

	public function __construct($href, $lang, \Youstice\Reports\ProductReport $report) {
		$this->href = $href;
		$this->translator = new \Youstice\Translator($lang);
		$this->report = $report;
	}

	public function toString() {
		if ($this->report->exists()) {
			if($this->report->getRemainingTime() == 0) {
				return $this->renderReportedButton();
			}

			return $this->renderReportedButtonWithTimeString();
		}

		return $this->renderUnreportedButton();
	}

	protected function renderReportedButton() {
		$status = $this->report->getStatus();
		$statusCssClass = "yrsButton-" . \Youstice\Helpers\HelperFunctions::webalize($status);

		$message = $this->translator->t($status);

		$output = '<a class="yrsButton ' . $statusCssClass . '" target="_blank" '
				. 'href="' . $this->href . '">'  . HelperFunctions::sh($message) . '</a>';

		return $output;
	}

	protected function renderReportedButtonWithTimeString() {
		$status = $this->report->getStatus();
		$message = $this->translator->t($status);
		$statusCssClass = "yrsButton-" . \Youstice\Helpers\HelperFunctions::webalize($status);
		$remainingTimeString = \Youstice\Helpers\HelperFunctions::remainingTimeToString($this->report->getRemainingTime(), $this->translator);

		$output = '<a class="yrsButton yrsButton-with-time ' . $statusCssClass . '" target="_blank" '
				. 'href="' . $this->href . '">'
				. '<span>' . HelperFunctions::sh($message) . '</span>'
				. '<span>' . HelperFunctions::sh($remainingTimeString) . '</span></a>';

		return $output;
	}

	protected function renderUnreportedButton() {

		$message = $this->translator->t('Report a problem');

		$output = '<a class="yrsButton" target="_blank" '
				. 'href="' . $this->href . '">' . HelperFunctions::sh($message) . '</a>';

		return $output;
	}

}
