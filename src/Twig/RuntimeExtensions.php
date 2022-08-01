<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig;

use App\Twig\Runtime\EncoreExtension;
use App\Twig\Runtime\MarkdownExtension;
use App\Twig\Runtime\ThemeExtension;
use App\Twig\Runtime\TimesheetExtension;
use App\Twig\Runtime\WidgetExtension;
use KevinPapst\TablerBundle\Twig\RuntimeExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RuntimeExtensions extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('trigger', [ThemeExtension::class, 'trigger'], ['needs_environment' => true]),
            new TwigFunction('actions', [ThemeExtension::class, 'actions']),
            new TwigFunction('get_title', [ThemeExtension::class, 'generateTitle']),
            new TwigFunction('progressbar_color', [ThemeExtension::class, 'getProgressbarClass']),
            new TwigFunction('javascript_translations', [ThemeExtension::class, 'getJavascriptTranslations']),
            new TwigFunction('form_time_presets', [ThemeExtension::class, 'getTimePresets']),
            new TwigFunction('active_timesheets', [TimesheetExtension::class, 'activeEntries']),
            new TwigFunction('favorite_timesheets', [TimesheetExtension::class, 'favoriteEntries']),
            new TwigFunction('encore_entry_css_source', [EncoreExtension::class, 'getEncoreEntryCssSource']),
            new TwigFunction('render_widget', [WidgetExtension::class, 'renderWidget'], ['is_safe' => ['html'], 'needs_environment' => true]),
            new TwigFunction('icon', [RuntimeExtension::class, 'createIcon'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('md2html', [MarkdownExtension::class, 'markdownToHtml'], ['pre_escape' => 'html', 'is_safe' => ['html']]),
            new TwigFilter('desc2html', [MarkdownExtension::class, 'timesheetContent'], ['pre_escape' => 'html', 'is_safe' => ['html']]),
            new TwigFilter('comment2html', [MarkdownExtension::class, 'commentContent'], ['pre_escape' => 'html', 'is_safe' => ['html']]),
            new TwigFilter('comment1line', [MarkdownExtension::class, 'commentOneLiner'], ['pre_escape' => 'html', 'is_safe' => ['html']]),
            new TwigFilter('colorize', [ThemeExtension::class, 'colorize']),
            new TwigFilter('icon', [RuntimeExtension::class, 'icon']),
        ];
    }
}
