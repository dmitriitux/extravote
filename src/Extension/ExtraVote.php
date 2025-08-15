<?php

namespace Joomla\Plugin\System\ExtraVote\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

class ExtraVote extends CMSPlugin implements SubscriberInterface
{

	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		return [
			'onAjaxExtravote' => 'onAjaxExtravote',
			'onAfterRender'   => 'onAfterRender',
			'onBeforeRender'  => 'onBeforeRender',
		];
	}

	public function onAfterRender()
	{
		$admin      = $this->getApplication()->isClient('administrator');
		$customizer = !empty($this->getApplication()->input->get('customizer'));

		if ($admin || $customizer)
		{
			return;
		}

		$body = $this->getApplication()->getBody();

		if (!str_contains($body, '{extravote'))
		{
			return;
		}

		$regex = "/{extravote\s*([0-9]+)}/i";

		$body = preg_replace_callback(
			$regex,
			[$this, 'replacer'],
			$body
		);

		$this->getApplication()->setBody($body);
	}

	public function onBeforeRender()
	{
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
		$wa->getRegistry()
			->addExtensionRegistryFile('plg_system_extravote');

		$wa->useScript('plg_system_extravote.js');

		if ((int) $this->params->get('css', 1))
		{
			$wa->useStyle('plg_system_extravote.style');
		}

		$wa->addInlineScript(
			"
            var ev_basefolder = '" . URI::base(true) . "';
            var extravote_text=Array('" .
			TEXT::_('PLG_SYSTEM_EXTRAVOTE_MESSAGE_NO_AJAX') . "','" .
			TEXT::_('PLG_SYSTEM_EXTRAVOTE_MESSAGE_LOADING') . "','" .
			TEXT::_('PLG_SYSTEM_EXTRAVOTE_MESSAGE_THANKS') . "','" .
			TEXT::_('PLG_SYSTEM_EXTRAVOTE_MESSAGE_LOGIN') . "','" .
			TEXT::_('PLG_SYSTEM_EXTRAVOTE_MESSAGE_RATED') . "','" .
			TEXT::_('PLG_SYSTEM_EXTRAVOTE_LABEL_VOTES') . "','" .
			TEXT::_('PLG_SYSTEM_EXTRAVOTE_LABEL_VOTE') . "','" .
			TEXT::_('PLG_SYSTEM_EXTRAVOTE_LABEL_RATING') .
			"');
        "
		);
	}

	protected function renderStars($id, $rating_sum, $rating_count, $ip)
	{
		$document = $this->getApplication()->getDocument();

		$document->addScript(URI::root(true) . '/plugins/content/extravote/assets/extravote.js');

		$show_counter = $this->params->get('show_counter', 1);
		$show_rating  = $this->params->get('show_rating', 1);
		$rating_mode  = $this->params->get('rating_mode', 1);
		$show_unrated = $this->params->get('show_unrated', 1);
		$initial_hide = $this->params->get('initial_hide', 0);
		$currip       = $_SERVER['REMOTE_ADDR'];
		$add_snippets = 0;
		$rating       = 0;


		if ($rating_count != 0)
		{
			$rating       = ($rating_sum / intval($rating_count));
			$add_snippets = $this->params->get('snippets', 0);
		}
		elseif ($show_unrated == 0)
		{
			$show_counter = -1;
			$show_rating  = -1;
		}

		$container = 'div';
		$class     = 'size-' . $this->params->get('size', 1);

		if ($show_counter == 3)
		{
			$show_counter = 0;
		}
		if ($show_rating == 3)
		{
			$show_rating = 0;
		}

		$class .= ' extravote';

		$stars = $this->params->get('stars', 10);
		$spans = '';

		for ($i = 0, $j = 5 / $stars; $i < $stars; $i++, $j += 5 / $stars) :
			$spans .= "
      <span class=\"extravote-star\"><a href=\"javascript:void(null)\" onclick=\"javascript:JVXVote(" . $id . "," . $j . "," . $rating_sum . "," . $rating_count . ",'" . $show_counter . "," . $show_rating . "," . $rating_mode . ");\" title=\"" . Text::_(
					'PLG_SYSTEM_EXTRAVOTE_RATING_' . ($j * 10) . '_OUT_OF_5'
				) . "\" class=\"ev-" . ($j * 10) . "-stars\">1</a></span>";
		endfor;

		$html = "
<" . $container . " class=\"" . $class . "\">
  <span class=\"extravote-stars\"" . ($add_snippets ? " itemprop=\"aggregateRating\" itemscope itemtype=\"http://schema.org/AggregateRating\"" : "") . ">" . ($add_snippets ? "
  	<meta itemprop=\"ratingCount\" content=\"" . $rating_count . "\" />
	" : "
	") . "<span id=\"rating_" . $id . "\" class=\"current-rating\"" . ((!$initial_hide || $currip == $ip) ? " style=\"width:" . round(
					$rating * 20
				) . "%;\"" : "") . "" . ($add_snippets ? " itemprop=\"ratingValue\"" : "") . ">" . ($add_snippets ? $rating : "") . "</span>"
			. $spans . "
  </span>
  <span class=\"extravote-info" . (($initial_hide && $currip != $ip) ? " ihide\"" : "") . "\" id=\"extravote_" . $id . "\">";

		if ($show_rating > 0)
		{
			if ($rating_mode == 0)
			{
				$rating = round($rating * 20) . '%';
			}
			else
			{
				$rating = number_format($rating, 2);
			}
			$html .= Text::sprintf('PLG_SYSTEM_EXTRAVOTE_LABEL_RATING', $rating);
		}

		if ($show_counter > 0)
		{
			if ($rating_count != 1)
			{
				$html .= Text::sprintf('PLG_SYSTEM_EXTRAVOTE_LABEL_VOTES', $rating_count);
			}
			else
			{
				$html .= Text::sprintf('PLG_SYSTEM_EXTRAVOTE_LABEL_VOTE', $rating_count);
			}
		}

		$html .= "</span>";
		$html .= "
</" . $container . ">";

		return $html;
	}

	protected function replacer(&$matches)
	{
		$db  = Factory::getContainer()->get(DatabaseInterface::class);
		$cid = 0;

		if (isset($matches[1]))
		{
			if (stripos($matches[0], 'extravote'))
			{
				$cid = (int) $matches[1];
			}
		}

		$rating_sum   = 0;
		$rating_count = 0;

		$db->setQuery(
			'SELECT * FROM #__content_extravote WHERE content_id=' . (int) $cid
		);

		$vote = $db->loadObject();
		if ($vote)
		{
			if ($vote->rating_count != 0)
			{
				$rating_sum = $vote->rating_sum;
			}
			$rating_count = intval($vote->rating_count);
		}

		return $this->renderStars($cid, $rating_sum, $rating_count, ($vote ? $vote->lastip : ''));
	}

	public function onAjaxExtravote()
	{
		$user = $this->getApplication()->getIdentity();

		if ($this->params->get('access') == 1 && $user->id > 0)
		{
			echo 'login';
		}
		else
		{
			$user_rating = (int) $this->getApplication()->input->getCmd('user_rating');
			$cid = (int) $this->getApplication()->input->getCmd('cid');

			if ($user_rating === 0 || $cid === 0)
			{
				echo 'fail';
				$this->getApplication()->close();
			}

			$db = Factory::getContainer()->get(DatabaseInterface::class);
			if ($user_rating >= 0.5 && $user_rating <= 5)
			{
				$currip = $_SERVER['REMOTE_ADDR'];
				$query  = "SELECT * FROM #__content_extravote WHERE content_id = " . $cid;
				$db->setQuery($query);
				$votesdb = $db->loadObject();
				if (!$votesdb)
				{
					$query = "INSERT INTO #__content_extravote ( content_id, extra_id, lastip, rating_sum, rating_count )"
						. "\n VALUES ( " . $cid . ", " . $db->Quote(
							$currip
						) . ", " . $user_rating . ", 1 )";
					$db->setQuery($query);
					$db->execute() or die();
				}
				else
				{
					if ($currip != ($votesdb->lastip))
					{
						$query = "UPDATE #__content_extravote"
							. "\n SET rating_count = rating_count + 1, rating_sum = rating_sum + " . $user_rating . ", lastip = " . $db->Quote(
								$currip
							)
							. "\n WHERE content_id = " . $cid;
						$db->setQuery($query);
						$db->execute() or die();
					}
					else
					{
						echo 'voted';
						exit();
					}
				}
				echo 'thanks';
			}
		}

		$this->getApplication()->close();
	}

}
