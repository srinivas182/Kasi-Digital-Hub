import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';

import { PhoneInput } from '../PhoneInput';

describe('PhoneInput', () => {
    it('reports E.164 for a valid local number and formats it on blur', () => {
        const onChange = vi.fn();
        render(<PhoneInput value="" onChange={onChange} aria-label="Cellphone" />);
        const input = screen.getByLabelText('Cellphone');

        fireEvent.change(input, { target: { value: '0724183390' } });
        expect(onChange).toHaveBeenLastCalledWith('+27724183390', '0724183390');

        fireEvent.blur(input);
        expect(input).toHaveValue('072 418 3390');
    });

    it('reports an empty value while the number is incomplete', () => {
        const onChange = vi.fn();
        render(<PhoneInput value="" onChange={onChange} aria-label="Cellphone" />);
        fireEvent.change(screen.getByLabelText('Cellphone'), { target: { value: '072 41' } });
        expect(onChange).toHaveBeenLastCalledWith('', '072 41');
    });
});
