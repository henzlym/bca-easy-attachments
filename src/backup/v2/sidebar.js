import { SearchUI } from "@sidebar/components";
import { useFetch } from "@sidebar/hooks";
import { Diaphragm } from "@sidebar/icons";
import { select } from "@wordpress/data";
import {
	store as editor,
	PluginSidebar,
	PluginSidebarMoreMenuItem,
} from "@wordpress/editor";
import { Fragment, useState } from "@wordpress/element";
import { addQueryArgs } from "@wordpress/url";

import Image from "../sidebar/Image";

import "../sidebar/editor.scss";

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
				Authorization:
					"Client-ID bf623eb6ee39cc322bb85c8e4575cda12670ee12cbfd85376bac4a022400edde",
			},
		},
		searchTerm: "",
	});
	const {
		baseURI,
		images,
		isDownloaded,
		isDownloading,
		options,
		path,
		searchTerm,
	} = state;
	const url = baseURI + path;
	const { data } = useFetch(url, options);

	const downloadImage = (photo, action = "") => {
		fetch(baseURI + `photos/${photo.id}/download`, options)
			.then((res) => res.json())
			.then((response) => {
				if (response) {
					const currentPostID = select(editor).getCurrentPostId();
					setState({ ...state, isDownloading: photo.id });

					// Use dynamically provided REST URL (handles both permalink formats)
					const restUrl =
						window.easyAttachmentsConfig?.restUrl ||
						"/wp-json/easy-attachments/v1/download";

					fetch(restUrl, {
						method: "POST",
						body: JSON.stringify({
							post_id: currentPostID,
							photo: photo,
							download_link: photo.urls.full,
						}),
						headers: {
							"X-WP-Nonce": blkcanvasGlobal.nonce,
							Accept: "application/json",
							"Content-Type": "application/json",
						},
					})
						.then((response) => {
							if (!response.ok) {
								throw new Error(`HTTP error! status: ${response.status}`);
							}
							return response.json();
						})
						.then((results) => {
							console.log("Download response:", results);

							if (results.success && results.data) {
								if (action == "in-post") {
									let insertedBlock = wp.blocks.createBlock("core/image", {
										id: results.data.id,
										url: results.data.url,
										alt: results.data.alt,
										caption: results.data.caption,
									});
									wp.data.dispatch("core/editor").insertBlocks(insertedBlock);
								}
								if (action == "featured-image") {
									wp.data
										.dispatch("core/editor")
										.editPost({ featured_media: results.data.id });
								}
								setState({
									...state,
									isDownloaded: photo.id,
									isDownloading: false,
								});

								wp.data
									.dispatch("core/notices")
									.createNotice("success", results.message, {
										isDismissible: true,
									});
							} else {
								throw new Error(results.message || "Download failed");
							}
						})
						.catch((error) => {
							console.error("Download error:", error);
							setState({ ...state, isDownloading: false });

							wp.data
								.dispatch("core/notices")
								.createNotice(
									"error",
									error.message || "Failed to download image",
									{
										isDismissible: true,
									}
								);
						});
				}
			});
	};
	return (
		<Fragment>
			<PluginSidebarMoreMenuItem target="easy-attachments">
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
							if (data == null) return null;

							if (data.length == 0) return null;

							if (typeof data.errors !== "undefined") return null;

							let images = [];

							if (typeof data.results !== "undefined") {
								images = [...data.results];
							} else {
								images = [...data];
							}

							return (
								<div className="easy-attachments-sidebar_photos">
									{images.map((image, i) => {
										return (
											<Image
												isDownloading={isDownloading}
												isDownloaded={isDownloaded}
												photo={image}
												download={downloadImage}
											/>
										);
									})}
								</div>
							);
						}}
						value={searchTerm}
						onChange={(query) => {
							const newPath = query.length ? "search/photos" : path;

							setState({
								...state,
								searchTerm: query,
								path: addQueryArgs(`${newPath}`, { query }),
							});
						}}
					/>
				</div>
			</PluginSidebar>
		</Fragment>
	);
}

export default Sidebar;
