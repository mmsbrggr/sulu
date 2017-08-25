// @flow
import React from 'react';
import type {ElementRef} from 'react';
import Portal from 'react-portal';
import {observer} from 'mobx-react';
import {action, computed, observable} from 'mobx';
import {afterElementsRendered} from '../../services/DOM';
import Backdrop from '../Backdrop';
import Option from './Option';
import overlayListStyles from './overlayList.scss';
import type {OverlayListDimensions, SelectChildren} from './types';
import OverlayListPositioner from './OverlayListPositioner';

type Props = {
    isOpen: boolean,
    children: SelectChildren,
    onRequestClose?: () => void,
    /** The top coordinate relative to which the list will be positioned */
    anchorTop: number,
    /** The left coordinate relative to which the list will be positioned */
    anchorLeft: number,
    /** The width of the element relative to which the list will be positioned */
    anchorWidth: number,
    /** The width of the element relative to which the list will be positioned */
    anchorHeight: number,
    /** The index of the child element which will be centered relative to the anchor */
    centeredChildIndex: number,
};

@observer
export default class OverlayList extends React.PureComponent<Props> {
    static defaultProps = {
        isOpen: false,
        centeredChildIndex: 0,
        anchorTop: 0,
        anchorLeft: 0,
        anchorWidth: 0,
        anchorHeight: 0,
    };

    @observable scrollHeight: number;
    @observable scrollWidth: number;
    @observable centeredChildRelativeTop: number;
    @observable isVisible: boolean = false;
    list: ElementRef<'ul'>;
    list: ?HTMLElement;
    scrollTop: number;

    get listBorderWidth(): number {
        return parseInt(window.getComputedStyle(this.list).borderLeftWidth);
    }

    componentDidMount() {
        window.addEventListener('blur', this.requestClose);
        window.addEventListener('resize', this.requestClose);
    }

    componentWillUnmount() {
        window.removeEventListener('blur', this.requestClose);
        window.removeEventListener('resize', this.requestClose);
    }

    @action componentWillReceiveProps(newProps: Props) {
        if (this.props.isOpen && !newProps.isOpen) {
            this.isVisible = false;
        }
    }

    componentDidUpdate() {
        afterElementsRendered(() => {
            if (this.list) {
                this.list.scrollTop = this.scrollTop || 0;
            }
        });
    }

    requestClose = () => {
        if (this.props.isOpen && this.props.onRequestClose) {
            this.props.onRequestClose();
        }
    };

    @computed get dimensions(): OverlayListDimensions {
        return OverlayListPositioner.getCroppedDimensions(
            this.scrollHeight,
            this.scrollWidth,
            this.centeredChildRelativeTop,
            this.props.anchorTop,
            this.props.anchorLeft,
            this.props.anchorWidth,
            this.props.anchorHeight,
        );
    }

    readCenteredChildRelativeTop = (option: ElementRef<typeof Option>) => {
        afterElementsRendered(action(() => {
            if (option) {
                this.centeredChildRelativeTop = option.getOffsetTop();
                this.isVisible = true;
            }
        }));
    };

    readOffsetDimensions = (list: ElementRef<'ul'>) => {
        this.list = list;
        afterElementsRendered(action(() => {
            if (list) {
                this.scrollWidth = list.scrollWidth + 2 * this.listBorderWidth;
                this.scrollHeight = list.scrollHeight + 2 * this.listBorderWidth;
            }
        }));
    };

    handleBackropClick = this.requestClose;

    render() {
        let style = {opacity: '0'};
        if (this.isVisible) {
            const dimensions = this.dimensions;
            style = OverlayListPositioner.dimensionsToStyle(dimensions);
            this.scrollTop = dimensions.scrollTop;
        }

        return (
            <div>
                <Portal isOpened={this.props.isOpen}>
                    <div className={overlayListStyles.container}>
                        <ul
                            ref={this.readOffsetDimensions}
                            style={style}
                            className={overlayListStyles.list}>
                            {this.renderChildrenWithFocusSet()}
                        </ul>
                    </div>
                </Portal>
                <Backdrop isVisible={false} isOpen={this.props.isOpen} onClick={this.handleBackropClick} />
            </div>
        );
    }

    renderChildrenWithFocusSet() {
        const children: any = React.Children.toArray(this.props.children);
        const centeredChildIsDisabled = children[this.props.centeredChildIndex].props.disabled;
        let focus = true;

        return React.Children.map(this.props.children, (child: any, index) => {
            const props = {};

            if (index === this.props.centeredChildIndex) {
                props.ref = this.readCenteredChildRelativeTop;
                props.focus = !centeredChildIsDisabled;
            }

            // if the child which gets centered is disabled, the first not disabled element receives the focus
            if (centeredChildIsDisabled && !child.props.disabled) {
                props.focus = focus;
                focus = false;
            }
            return React.cloneElement(child, props);
        });
    }
}
