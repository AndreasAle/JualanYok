<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case GrossSale = 'GROSS_SALE';
    case PlatformFee = 'PLATFORM_FEE';
    case PaymentFee = 'PAYMENT_FEE';
    case SellerRevenue = 'SELLER_REVENUE';
    case AffiliateCommission = 'AFFILIATE_COMMISSION';
    case CommissionReversal = 'COMMISSION_REVERSAL';
    case Refund = 'REFUND';
    case Adjustment = 'ADJUSTMENT';
    case Release = 'RELEASE';
    case Reserve = 'RESERVE';
    case ReserveRelease = 'RESERVE_RELEASE';
    case Debt = 'DEBT';
    case DebtRecovery = 'DEBT_RECOVERY';
    case Withdrawal = 'WITHDRAWAL';
    case WithdrawalFee = 'WITHDRAWAL_FEE';
    case WithdrawalReversal = 'WITHDRAWAL_REVERSAL';
    case SubscriptionPayment = 'SUBSCRIPTION_PAYMENT';

    public function label(): string
    {
        return match ($this) {
            self::GrossSale => 'Penjualan Kotor',
            self::PlatformFee => 'Biaya Platform',
            self::PaymentFee => 'Biaya Pembayaran',
            self::SellerRevenue => 'Pendapatan Bersih',
            self::AffiliateCommission => 'Komisi Affiliate',
            self::CommissionReversal => 'Pembatalan Komisi',
            self::Refund => 'Refund',
            self::Adjustment => 'Penyesuaian',
            self::Release => 'Pencairan ke Saldo Tersedia',
            self::Reserve => 'Dana Cadangan Risiko',
            self::ReserveRelease => 'Pelepasan Dana Cadangan',
            self::Debt => 'Saldo Negatif',
            self::DebtRecovery => 'Pelunasan Saldo Negatif',
            self::Withdrawal => 'Penarikan',
            self::WithdrawalFee => 'Biaya Penarikan',
            self::WithdrawalReversal => 'Pengembalian Penarikan',
            self::SubscriptionPayment => 'Pembayaran Langganan',
        };
    }
}
