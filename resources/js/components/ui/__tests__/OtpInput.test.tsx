import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { vi } from 'vitest';

import { OtpInput } from '../OtpInput';

function Harness({ onComplete }: { onComplete: (code: string) => void }) {
    const [value, setValue] = useState('');
    return <OtpInput value={value} onChange={setValue} onComplete={onComplete} />;
}

describe('OtpInput', () => {
    it('moves to the next box as digits are typed and reports completion', () => {
        const onComplete = vi.fn();
        render(<Harness onComplete={onComplete} />);
        const boxes = screen.getAllByRole('textbox');
        expect(boxes).toHaveLength(6);

        '123456'.split('').forEach((digit, index) => {
            fireEvent.change(boxes[index]!, { target: { value: digit } });
        });

        expect(onComplete).toHaveBeenCalledWith('123456');
        expect(boxes[5]).toHaveValue('6');
    });

    it('accepts a pasted code', () => {
        const onComplete = vi.fn();
        render(<Harness onComplete={onComplete} />);
        fireEvent.paste(screen.getAllByRole('textbox')[0]!, { clipboardData: { getData: () => '98 76 54' } });
        expect(onComplete).toHaveBeenCalledWith('987654');
    });

    it('ignores letters', () => {
        const onComplete = vi.fn();
        render(<Harness onComplete={onComplete} />);
        const first = screen.getAllByRole('textbox')[0]!;
        fireEvent.change(first, { target: { value: 'a' } });
        expect(first).toHaveValue('');
    });

    it('labels every box for screen readers', () => {
        render(<Harness onComplete={vi.fn()} />);
        expect(screen.getByLabelText('Verification code digit 1 of 6')).toBeInTheDocument();
    });
});
