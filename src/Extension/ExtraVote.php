<?php

namespace Joomla\Plugin\System\ExtraVote\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

class ExtraVote extends CMSPlugin implements SubscriberInterface
{

	protected $autoloadLanguage = true;

	protected int $article_id;

	protected string $view;

	public static function getSubscribedEvents(): array
	{
		return [
			'onAjaxExtravote' => 'onAjaxExtravote',
			'onAfterRender'   => 'onAfterRender',
		];
	}

	protected function renderStars($id, $rating_sum, $rating_count, $xid, $ip)
	{
		$document = JFactory::getDocument();

		$document->addScript(URI::root(true) . '/plugins/content/extravote/assets/extravote.js');

		global $plgContentExtraVoteAddScript;

		$show_counter = $this->params->get('show_counter', 1);
		$show_rating  = $this->params->get('show_rating', 1);
		$rating_mode  = $this->params->get('rating_mode', 1);
		$show_unrated = $this->params->get('show_unrated', 1);
		$initial_hide = $this->params->get('initial_hide', 0);
		$currip       = $_SERVER['REMOTE_ADDR'];
		$add_snippets = 0;
		$rating       = 0;

		if (!$plgContentExtraVoteAddScript)
		{
			$document->addScriptDeclaration(
				"
				var ev_basefolder = '" . JURI::base(true) . "';
				var extravote_text=Array('" .
				Text::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_NO_AJAX') . "','" .
				Text::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_LOADING') . "','" .
				Text::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_THANKS') . "','" .
				Text::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_LOGIN') . "','" .
				Text::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_RATED') . "','" .
				Text::_('PLG_CONTENT_EXTRAVOTE_LABEL_VOTES') . "','" .
				Text::_('PLG_CONTENT_EXTRAVOTE_LABEL_VOTE') . "','" .
				Text::_('PLG_CONTENT_EXTRAVOTE_LABEL_RATING') .
				"');
			"
			);
			$plgContentExtraVoteAddScript = 1;
		}

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

		if ((int) $xid)
		{
			if ($show_counter == 2)
			{
				$show_counter = 0;
			}
			if ($show_rating == 2)
			{
				$show_rating = 0;
			}
			$container    = 'span';
			$class        .= ' extravote';
			$add_snippets = 0;
		}
		else
		{
			if ($show_counter == 3)
			{
				$show_counter = 0;
			}
			if ($show_rating == 3)
			{
				$show_rating = 0;
			}
			$class .= ' extravote';
		}

		$stars = (($this->params->get('table', 1) !== 1 && !(int) $xid) ? 5 : $this->params->get('stars', 10));
		$spans = '';

		for ($i = 0, $j = 5 / $stars; $i < $stars; $i++, $j += 5 / $stars) :
			$spans .= "
      <span class=\"extravote-star\"><a href=\"javascript:void(null)\" onclick=\"javascript:JVXVote(" . $id . "," . $j . "," . $rating_sum . "," . $rating_count . ",'" . $xid . "'," . $show_counter . "," . $show_rating . "," . $rating_mode . ");\" title=\"" . Text::_(
					'PLG_CONTENT_EXTRAVOTE_RATING_' . ($j * 10) . '_OUT_OF_5'
				) . "\" class=\"ev-" . ($j * 10) . "-stars\">1</a></span>";
		endfor;

		$html = "
<" . $container . " class=\"" . $class . "\">
  <span class=\"extravote-stars\"" . ($add_snippets ? " itemprop=\"aggregateRating\" itemscope itemtype=\"http://schema.org/AggregateRating\"" : "") . ">" . ($add_snippets ? "
  	<meta itemprop=\"ratingCount\" content=\"" . $rating_count . "\" />
	" : "
	") . "<span id=\"rating_" . $id . "_" . $xid . "\" class=\"current-rating\"" . ((!$initial_hide || $currip == $ip) ? " style=\"width:" . round(
					$rating * 20
				) . "%;\"" : "") . "" . ($add_snippets ? " itemprop=\"ratingValue\"" : "") . ">" . ($add_snippets ? $rating : "") . "</span>"
			. $spans . "
  </span>
  <span class=\"extravote-info" . (($initial_hide && $currip != $ip) ? " ihide\"" : "") . "\" id=\"extravote_" . $id . "_" . $xid . "\">";

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
			$html .= Text::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_RATING', $rating);
		}

		if ($show_counter > 0)
		{
			if ($rating_count != 1)
			{
				$html .= Text::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_VOTES', $rating_count);
			}
			else
			{
				$html .= Text::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_VOTE', $rating_count);
			}
		}

		$html .= "</span>";
		$html .= "
</" . $container . ">";

		return $html;
	}

	protected function onAfterRender()
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

	protected function replacer(&$matches)
	{
		$db  = JFactory::getDBO();
		$cid = 0;
		$xid = 0;

		if (isset($matches[1]))
		{
			if (stripos($matches[0], 'extravote'))
			{
				$xid = (int) $matches[1];
			}
			else
			{
				$cid = (int) $matches[1];
			}
		}

		if ($cid == 0 && ($this->params->get('article_id') || $xid == 0))
		{
			$cid = $this->article_id;
		}

		$rating_sum   = 0;
		$rating_count = 0;

		if ($xid == 0) :
			global $extravote_mainvote;
			$extravote_mainvote .= 'x';
			$xid                = $extravote_mainvote;
			$table              = ($this->params->get('table', 1) == 1 ? '#__content_extravote' : '#__content_rating');
			$db->setQuery('SELECT * FROM ' . $table . ' WHERE content_id=' . (int) $cid);
		else :
			$db->setQuery(
				'SELECT * FROM #__content_extravote WHERE content_id=' . (int) $cid . ' AND extra_id=' . (int) $xid
			);
		endif;

		$vote = $db->loadObject();
		if ($vote)
		{
			if ($vote->rating_count != 0)
			{
				$rating_sum = $vote->rating_sum;
			}
			$rating_count = intval($vote->rating_count);
		}

		return $this->renderStars($cid, $rating_sum, $rating_count, $xid, ($vote ? $vote->lastip : ''));
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
			$cid         = 0;
			$xid         = (int) $this->getApplication()->input->getCmd('xid');

			if ($this->params->get('article_id') || $xid == 0)
			{
				$cid = (int) $this->getApplication()->input->getCmd('cid');
			}

			if ($user_rating === 0 || $cid === 0 || $xid === 0)
			{
				echo 'fail';
				$this->getApplication()->close();
			}
			$db = Factory::getDbo();
			if ($user_rating >= 0.5 && $user_rating <= 5)
			{
				$currip = $_SERVER['REMOTE_ADDR'];
				$query  = "SELECT * FROM #__content_extravote WHERE content_id = " . $cid . " AND extra_id = " . $xid;
				$db->setQuery($query);
				$votesdb = $db->loadObject();
				if (!$votesdb)
				{
					$query = "INSERT INTO #__content_extravote ( content_id, extra_id, lastip, rating_sum, rating_count )"
						. "\n VALUES ( " . $cid . ", " . $xid . ", " . $db->Quote(
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
							. "\n WHERE content_id = " . $cid . " AND extra_id = " . $xid;
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
