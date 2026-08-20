<?php

namespace App\Enums;

enum BlockType: string
{
    case Heading = 'HEADING';
    case Text = 'TEXT';
    case LinkButton = 'LINK_BUTTON';
    case SocialLinks = 'SOCIAL_LINKS';
    case Image = 'IMAGE';
    case Gallery = 'GALLERY';
    case Video = 'VIDEO';
    case Divider = 'DIVIDER';
    case Spacer = 'SPACER';
    case Faq = 'FAQ';
    case Testimonial = 'TESTIMONIAL';
    case Countdown = 'COUNTDOWN';
    case PromoBanner = 'PROMO_BANNER';
    case LeadForm = 'LEAD_FORM';
    case WhatsappCta = 'WHATSAPP_CTA';
    case Product = 'PRODUCT';
    case ProductCollection = 'PRODUCT_COLLECTION';
    case FeaturedProducts = 'FEATURED_PRODUCTS';
    case AffiliateProduct = 'AFFILIATE_PRODUCT';
    case Article = 'ARTICLE';
    case Embed = 'EMBED';

    public function label(): string
    {
        return match ($this) {
            self::Heading => 'Judul',
            self::Text => 'Teks',
            self::LinkButton => 'Tombol Link',
            self::SocialLinks => 'Sosial Media',
            self::Image => 'Gambar',
            self::Gallery => 'Galeri Gambar',
            self::Video => 'Video YouTube',
            self::Divider => 'Pemisah',
            self::Spacer => 'Jarak',
            self::Faq => 'FAQ',
            self::Testimonial => 'Testimoni',
            self::Countdown => 'Hitung Mundur',
            self::PromoBanner => 'Banner Promo',
            self::LeadForm => 'Form Leads',
            self::WhatsappCta => 'Tombol WhatsApp',
            self::Product => 'Produk',
            self::ProductCollection => 'Koleksi Produk',
            self::FeaturedProducts => 'Produk Unggulan',
            self::AffiliateProduct => 'Produk Affiliate',
            self::Article => 'Artikel',
            self::Embed => 'Embed',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Product, self::ProductCollection, self::FeaturedProducts, self::AffiliateProduct => 'Jualan',
            self::LeadForm, self::WhatsappCta, self::PromoBanner, self::Countdown => 'Marketing',
            self::Heading, self::Text, self::Image, self::Gallery, self::Video, self::Article => 'Konten',
            default => 'Lainnya',
        };
    }
}
