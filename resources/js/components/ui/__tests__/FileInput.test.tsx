import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';

import { FileInput } from '../FileInput';
import { Field } from '../form';

describe('FileInput', () => {
    it('reports the chosen file and shows its name and size', () => {
        const onChange = vi.fn();
        const file = new File(['x'.repeat(2048)], 'matric.pdf', { type: 'application/pdf' });
        const { rerender } = render(
            <Field label="Document">
                <FileInput file={null} onChange={onChange} chooseLabel="Choose file" photoLabel="Take a photo" />
            </Field>,
        );

        fireEvent.change(screen.getByLabelText('Document'), { target: { files: [file] } });
        expect(onChange).toHaveBeenCalledWith(file);

        rerender(
            <Field label="Document">
                <FileInput file={file} onChange={onChange} chooseLabel="Choose file" photoLabel="Take a photo" />
            </Field>,
        );
        expect(screen.getByText('matric.pdf')).toBeInTheDocument();
        expect(screen.getByText('(2 KB)')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Take a photo' })).toBeInTheDocument();
    });
});
