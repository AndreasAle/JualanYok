<?php

namespace App\Support;

/**
 * The design vocabulary a creator can apply to any block.
 *
 * Deliberately a fixed set of tokens rather than free-form CSS. `style` used to
 * be an open array rendered straight into an inline style attribute, which let
 * a creator write anything — including a fixed-position overlay that covers
 * their own checkout button while still looking fine in the preview. Tokens
 * cannot produce a broken page, and they let the storefront theme keep control
 * of colour so a block never clashes with the palette.
 */
class BlockStyle
{
    /** @var array<string, list<string>> */
    public const OPTIONS = [
        'background' => ['none', 'subtle', 'primary', 'accent', 'dark', 'gradient', 'outline'],
        'padding' => ['none', 'sm', 'md', 'lg', 'xl'],
        'radius' => ['none', 'sm', 'md', 'lg', 'xl'],
        'align' => ['left', 'center', 'right'],
        'width' => ['normal', 'narrow', 'wide', 'full'],
        'shadow' => ['none', 'soft', 'lift', 'glow'],
        'animation' => ['none', 'fade', 'slide-up', 'slide-left', 'slide-right', 'zoom', 'blur'],
        'animation_delay' => ['0', '100', '200', '300', '500'],
    ];

    public const DEFAULTS = [
        'background' => 'none',
        'padding' => 'none',
        'radius' => 'lg',
        'align' => 'left',
        'width' => 'normal',
        'shadow' => 'none',
        'animation' => 'none',
        'animation_delay' => '0',
    ];

    /**
     * Keeps only recognised keys with recognised values.
     *
     * Anything unknown is dropped rather than rejected, so an older client that
     * still sends a retired token cannot fail a save the creator was mid-way
     * through.
     *
     * @param  mixed  $style
     * @return array<string, string>
     */
    public static function sanitise($style): array
    {
        if (! is_array($style)) {
            return [];
        }

        $clean = [];

        foreach (self::OPTIONS as $key => $allowed) {
            $value = $style[$key] ?? null;

            if (is_string($value) && in_array($value, $allowed, true)) {
                $clean[$key] = $value;
            } elseif (is_int($value) && in_array((string) $value, $allowed, true)) {
                $clean[$key] = (string) $value;
            }
        }

        return $clean;
    }

    /** The full set a renderer can rely on, with gaps filled in. */
    public static function resolve($style): array
    {
        return array_merge(self::DEFAULTS, self::sanitise($style));
    }

    /** Shape the builder needs to draw its controls. */
    public static function schema(): array
    {
        return [
            'options' => self::OPTIONS,
            'defaults' => self::DEFAULTS,
        ];
    }
}
