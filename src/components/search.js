import { Fragment, useEffect, useState } from '@wordpress/element';
import { SearchControl } from '@wordpress/components';

function Search(props) {

    const { onChange, results, value } = props;

    return (
        <Fragment>
            <SearchControl
                className="easy-attachments-sidebar_search-container"
                value={value}
                onChange={onChange}
            />
            { results() }
        </Fragment>

    )
}

export default Search;