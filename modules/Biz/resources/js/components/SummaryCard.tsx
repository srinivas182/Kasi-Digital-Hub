import { Card } from '@/components/ui/display';
import { formatDate } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';

export interface Summary {
    business: string;
    months_recorded: number;
    turnover_band: string | null;
    profitable_months: number;
    of_months: number;
    stock_band: string | null;
    compliance_up_to_date: boolean;
    overdue: string[];
    market_rating: number | null;
    as_of: string;
}

/** The shared business summary: bands and counts only (S29). The owner's preview and the recipient see the same. */
export function SummaryCard({ summary: s }: { summary: Summary }) {
    const { t } = useTranslation();
    const row = (label: string, value: string) => (
        <div className="border-line flex justify-between gap-3 border-b py-2 text-sm">
            <dt className="text-fg-muted">{label}</dt>
            <dd className="text-fg text-right font-semibold">{value}</dd>
        </div>
    );
    return (
        <Card className="mt-2">
            <p className="text-fg text-lg font-bold">{s.business}</p>
            <dl className="mt-2">
                {row(t('biz.summary.months'), t('biz.summary.months_value', { n: s.months_recorded }))}
                {row(
                    t('biz.summary.turnover'),
                    s.turnover_band ? t(`biz.band.${s.turnover_band}`) : t('biz.summary.unknown'),
                )}
                {row(
                    t('biz.summary.profitable'),
                    t('biz.summary.profitable_value', { n: s.profitable_months, of: s.of_months }),
                )}
                {row(
                    t('biz.summary.stock'),
                    s.stock_band ? t(`biz.stock_band.${s.stock_band}`) : t('biz.summary.unknown'),
                )}
                {row(
                    t('biz.summary.compliance'),
                    s.compliance_up_to_date
                        ? t('biz.summary.up_to_date')
                        : t('biz.summary.overdue', {
                              items: s.overdue.map((r) => t(`biz.compliance.rule.${r}`)).join(', '),
                          }),
                )}
                {s.market_rating !== null && row(t('biz.summary.rating'), `${s.market_rating} / 5`)}
            </dl>
            <p className="text-fg-muted mt-2 text-xs">{t('biz.summary.footer', { date: formatDate(s.as_of) })}</p>
        </Card>
    );
}
