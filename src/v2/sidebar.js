import { Fragment, useEffect, useState } from '@wordpress/element';
import { DOWN, ENTER } from '@wordpress/keycodes';
import { TextControl, Button } from '@wordpress/components';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { useDispatch, useSelect, dispatch, register, select } from '@wordpress/data';
import { store as core } from '@wordpress/core-data';
import { store as editor } from '@wordpress/editor';

import { useFetch } from '@sidebar/hooks';
import { Diaphragm } from "@sidebar/icons";
import { SearchUI } from '@sidebar/components';

import Image from '../sidebar/Image';

import '../sidebar/editor.scss';

function Sidebar() {
    const [state, setState] = useState({
        baseURI: "https://api.unsplash.com/",
        path: "photos",
        categories: [],
        images: [],
        isDownloading: false,
        isDownloaded: false,
        options: {
            headers: {
                "Authorization": "Client-ID bf623eb6ee39cc322bb85c8e4575cda12670ee12cbfd85376bac4a022400edde"
            }
        },
        searchTerm: "",
    });
    const { baseURI, images, isDownloaded, isDownloading, options, path, searchTerm } = state;
    const url = baseURI + path;
    const { data } = useFetch(url, options);
    console.log(data);

    const downloadImage = (photo, action = "") => {

        fetch(baseURI + `photos/${photo.id}/download`, options)
            .then(res => res.json())
            .then((response) => {

                if (response) {
                    const currentPostID = select(editor).getCurrentPostId()
                    setState({ ...state, isDownloading: photo.id })
                    fetch('/wp-json/easy-attachments/v1/download', {
                        method: 'POST',
                        body: JSON.stringify({ post_id: currentPostID, photo: photo, download_link: photo.urls.full }),
                        headers: {
                            'X-WP-Nonce': blkcanvasGlobal.nonce,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                    })
                        .then(response => response.json())
                        .then((results) => {
                            if (results) {
                                if (action == 'in-post') {
                                    let insertedBlock = wp.blocks.createBlock('core/image', {
                                        id: results.id,
                                        url: results.url,
                                        alt: results.alt,
                                        caption: results.caption,
                                    });
                                    wp.data.dispatch('core/editor').insertBlocks(insertedBlock);
                                }
                                if (action == 'featured-image') {
                                    wp.data.dispatch('core/editor').editPost({ featured_media: results.id });
                                }
                                setState({ ...state, isDownloaded: photo.id, isDownloading: false })

                            } else {
                                wp.data.dispatch('core/notices').createNotice(
                                    'error', // Can be one of: success, info, warning, error.
                                    results.msg, // Text string to display.
                                    {
                                        isDismissible: true, // Whether the user can dismiss the notice.
                                        // Any actions the user can perform.
                                    }
                                );
                            }
                        });
                }
            })
    }
    return (
        <Fragment>
            <PluginSidebarMoreMenuItem
                target="easy-attachments"
            >
                My Sidebar
            </PluginSidebarMoreMenuItem>
            <PluginSidebar

                name="easy-attachments"
                title="Easy Attachments"
                icon={<Diaphragm />}
            >
                <div className="easy-attachments-sidebar">
                    <SearchUI
                        results={() => {
                            if (data == null ) return null;
                            
                            if (data.length == 0) return null;
                            
                            return(
                                <div className="easy-attachments-sidebar_photos">
                                    {
                                        data.map((image, i) => {
                                            return (
                                                <Image
                                                    isDownloading={isDownloading}
                                                    isDownloaded={isDownloaded}
                                                    photo={image}
                                                    download={downloadImage}
                                                />
                                            )
                                        })
                                    }
                                </div>
                            );
                            
                        }}
                        value={searchTerm}
                        onChange={(term) => {
                            setState({ ...state, searchTerm: term })
                        }}
                    />
                </div>

            </PluginSidebar>
        </Fragment>
    )

}

export default Sidebar;
