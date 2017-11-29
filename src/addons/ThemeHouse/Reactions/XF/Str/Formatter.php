<?php

namespace ThemeHouse\Reactions\XF\Str;

class Formatter extends \XF\Str\Formatter
{
    protected $reactionCache = [];

    public function getReactionHtml($reaction, $styleProperties, $class = '', $hideDimensions = false)
    {
        if (isset($reactionCache[$reaction['reaction_id']])) {
            return $reactionCache[$reaction['reaction_id']];
        }

        $pather = $this->smilieHtmlPather;

        $openingTag = '<span class="reaction reaction' . (empty($class) ?: ' ' . $class) . '" title="' . $reaction['title'] . '" data-xf-init="tooltip">'; $closingTag = '</span>';

        if ($reaction['styling_type'] == 'image') {
            if ($reaction['image_type'] == 'normal') {
                $url = htmlspecialchars($pather ? $pather($reaction['image_url'], 'base') : $reaction['image_url']);
                $srcSet = '';
                if (!empty($reaction['image_url_2x'])) {
                    $url2x = htmlspecialchars($pather ? $pather($reaction['image_url_2x'], 'base') : $reaction['image_url_2x']);
                    $srcSet = 'srcset="' . $url2x . ' 2x"';
                }

                $width = $reaction['styling']['image_normal']['w'];
                $height = $reaction['styling']['image_normal']['h'];
                $unit = $reaction['styling']['image_normal']['u'];

                if (isset($reaction['styling']['image_normal']['style_dimensions'])) {
                    $width = $styleProperties['imageDimensions']['width'];
                    $height = $styleProperties['imageDimensions']['height'];
                    $unit = $styleProperties['imageDimensions']['unit'];
                }

                $reactionCache[$reaction['reaction_id']] = $openingTag . '<img src="' . $url . '" ' . $srcSet . ' class="reaction--normal reaction--normal' . $reaction['reaction_id']
                    . '" alt="' . $reaction['title'] . '" title="' . $reaction['title'] . '"'
                    . ($hideDimensions ? '' : ' width="' . $width . $unit . '" height="' . $height . $unit . '"') . '/>' . $closingTag;
            }

            if ($reaction['image_type'] == 'sprite') {
                // embed a data URI to avoid a request that doesn't respect paths fully
                $url = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
                $reactionCache[$reaction['reaction_id']] = $openingTag . '<img src="' . $url . '" class="reaction--sprite reaction--sprite' . $reaction['reaction_id']
                    . '" alt="' . $reaction['title'] . '" />' . $closingTag;
            }
        }

        if ($reaction['styling_type'] == 'text') {
            $reactionCache[$reaction['reaction_id']] = $openingTag . $reaction['reaction_text'] . $closingTag;
        }

        if ($reaction['styling_type'] == 'css_class') {
            $reactionCache[$reaction['reaction_id']] = $openingTag . '<i class="' . $reaction['reaction_text'] . '"></i>' . $closingTag;
        }

        if ($reaction['styling_type'] == 'html_css') {
            $reactionCache[$reaction['reaction_id']] = $openingTag . $reaction['styling']['html_css']['html'] . $closingTag;
        }

        return $reactionCache[$reaction['reaction_id']];
    }
}