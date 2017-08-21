/* eslint-disable flowtype/require-valid-file-annotation */
import {mount, render} from 'enzyme';
import React from 'react';
import RadioGroup from '../RadioGroup';
import Radio from '../Radio';

jest.mock('../Radio', () => function Radio() {
    return <p>My radio mock</p>;
});

test('The component should render', () => {
    const group = render(
        <RadioGroup className="my-group" value="1">
            <Radio value="1" />
            <Radio value="2" />
            <Radio value="3" />
        </RadioGroup>
    );
    expect(group).toMatchSnapshot();
});

test('The component should check the correct radio', () => {
    const group = mount(
        <RadioGroup value="1">
            <Radio value="1" />
            <Radio value="2" />
            <Radio value="3" />
        </RadioGroup>
    );
    const radios = group.find(Radio);
    expect(radios.get(0).props.checked).toBe(true);
    expect(radios.get(1).props.checked).toBe(false);
    expect(radios.get(2).props.checked).toBe(false);
});

test('The component should pass the change callback to the radios', () => {
    const onChange = () => 'my-on-change';
    const group = mount(
        <RadioGroup onChange={onChange}>
            <Radio value="1" />
            <Radio value="2" />
            <Radio value="3" />
        </RadioGroup>
    );
    const radios = group.find(Radio);
    expect(radios.get(0).props.onChange()).toBe('my-on-change');
    expect(radios.get(1).props.onChange()).toBe('my-on-change');
    expect(radios.get(2).props.onChange()).toBe('my-on-change');
});
