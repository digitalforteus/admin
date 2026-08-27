<?php

namespace App\Helpers;

/**
 * The appearance a user can choose and keep, and everything each choice implies.
 *
 * The case that defers to the device renders no theme attribute at all: leaving it
 * off is what hands the decision to the operating system, so that case must never
 * be given a name the stylesheet has not registered — and an unregistered name
 * renders an unstyled page. Presentation is answered by exhaustive matching, so a
 * new case fails loudly until every question is answered for it: its label, its
 * description, the icon it shows, and the browser-chrome color that has to stay
 * paired with the background the stylesheet paints. Values are stored, so one has
 * to fit the column. The choices are enumerated when rendered, so the form follows.
 */
enum Theme: string
{
    use HasEnumAttributes;

    #[ThemeLabel('Light')]
    #[ThemeDescription('Always use the light theme.')]
    #[ThemeColor('#fafcfe')]
    #[ThemeIcon(SvgName::sun)]
    case light = 'light';

    #[ThemeLabel('Dark')]
    #[ThemeDescription('Always use the dark theme.')]
    #[ThemeColor('#1b2025')]
    #[ThemeIcon(SvgName::moon)]
    case dark = 'dark';

    #[ThemeLabel('Auto')]
    #[ThemeDescription('Match the theme your device is set to.')]
    #[ThemeColor('#fafcfe')]
    #[ThemeIcon(SvgName::desktop)]
    case auto = 'auto';

    public function attribute(): ?string
    {
        return $this === self::auto ? null : $this->value;
    }

    public function label(): string
    {
        return $this->enumAttribute(ThemeLabel::class)->label;
    }

    public function description(): string
    {
        return $this->enumAttribute(ThemeDescription::class)->description;
    }

    public function color(): string
    {
        return $this->enumAttribute(ThemeColor::class)->color;
    }

    public function icon(): SvgName
    {
        return $this->enumAttribute(ThemeIcon::class)->icon;
    }
}
