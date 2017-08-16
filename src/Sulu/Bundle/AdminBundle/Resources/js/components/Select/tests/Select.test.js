/* eslint-disable flowtype/require-valid-file-annotation */
import {shallow} from 'enzyme';
import React from 'react';
import GenericSelect from '../GenericSelect';
import Select from '../Select';
import Divider from '../Divider';
import Option from '../Option';

jest.mock('../GenericSelect');

test('The component should render a generic select', () => {
    const select = shallow(
        <Select>
            <Option value="option-1">Option 1</Option>
            <Option value="option-2">Option 2</Option>
            <Divider />
            <Option value="option-3">Option 3</Option>
        </Select>
    );
    expect(select.node.type).toBe(GenericSelect);
});

test('The component should return the first option as default label', () => {
    const select = shallow(
        <Select>
            <Option value="option-1">Option 1</Option>
            <Option value="option-2">Option 2</Option>
            <Divider />
            <Option value="option-3">Option 3</Option>
        </Select>
    );
    const label = select.find(GenericSelect).props().getLabelText();
    expect(label).toBe('Option 1');
});

test('The component should return the correct label', () => {
    const select = shallow(
        <Select value="option-2">
            <Option value="option-1">Option 1</Option>
            <Option value="option-2">Option 2</Option>
            <Divider />
            <Option value="option-3">Option 3</Option>
        </Select>
    );
    const label = select.find(GenericSelect).props().getLabelText();
    expect(label).toBe('Option 2');
});

test('The component should select the correct option', () => {
    const select = shallow(
        <Select value="option-2">
            <Option value="option-1">Option 1</Option>
            <Option value="option-2">Option 2</Option>
            <Divider />
            <Option value="option-3">Option 3</Option>
        </Select>
    );
    const optionIsSelected = select.find(GenericSelect).props().optionIsSelected;
    expect(optionIsSelected({props: {value: 'option-1', disabled: false}})).toBe(false);
    expect(optionIsSelected({props: {value: 'option-2', disabled: false}})).toBe(true);
    expect(optionIsSelected({props: {value: 'option-3', disabled: false}})).toBe(false);
});

test('The component should trigger the change callback on select', () => {
    const onChangeSpy = jest.fn();
    const select = shallow(
        <Select value="option-2" onChange={onChangeSpy}>
            <Option value="option-1">Option 1</Option>
            <Option value="option-2">Option 2</Option>
            <Divider />
            <Option value="option-3">Option 3</Option>
        </Select>
    );
    select.find(GenericSelect).props().onSelect('option-3');
    expect(onChangeSpy).toHaveBeenCalledWith('option-3');
});
