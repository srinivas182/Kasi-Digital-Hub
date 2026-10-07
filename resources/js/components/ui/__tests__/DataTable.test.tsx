import { render, screen, within } from '@testing-library/react';

import { DataTable } from '../DataTable';

const rows = [
    { id: 1, name: 'Thandi', hub: 'Tsutsumani' },
    { id: 2, name: 'Lwazi', hub: 'Giyani' },
];

describe('DataTable', () => {
    it('renders a captioned table and a stacked list, hiding mobile-hidden columns in the list', () => {
        render(
            <DataTable
                caption="Candidates"
                rows={rows}
                rowKey={(row) => row.id}
                columns={[
                    { key: 'name', header: 'Name', cell: (row) => row.name },
                    { key: 'hub', header: 'Hub', cell: (row) => row.hub, hideOnMobile: true },
                ]}
            />,
        );

        const table = screen.getByRole('table', { name: 'Candidates' });
        expect(within(table).getAllByRole('row')).toHaveLength(3);

        const list = screen.getByRole('list', { name: 'Candidates' });
        expect(within(list).getByText('Thandi')).toBeInTheDocument();
        expect(within(list).queryByText('Tsutsumani')).not.toBeInTheDocument();
    });

    it('shows the empty state when there are no rows', () => {
        render(
            <DataTable caption="Candidates" rows={[]} rowKey={() => 0} columns={[]} empty={<p>No candidates yet</p>} />,
        );
        expect(screen.getByText('No candidates yet')).toBeInTheDocument();
    });
});
