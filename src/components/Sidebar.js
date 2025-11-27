/**
 * Main Sidebar Component
 *
 * Provides an interface for searching and downloading images from Unsplash
 * directly into the WordPress media library.
 *
 * @since 1.0.0
 * @package EasyAttachments
 */

import { SearchControl } from "@wordpress/components";
import { useDispatch, useSelect } from "@wordpress/data";
import {
	store as editorStore,
	PluginSidebar,
	PluginSidebarMoreMenuItem,
} from "@wordpress/editor";
import {
	Fragment,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from "@wordpress/element";
import { addQueryArgs } from "@wordpress/url";

import useFetch from "../hooks/useFetch";
import { Diaphragm } from "../icons";
import ImageItem from "./ImageItem";

import "../styles/sidebar.scss";

/**
 * Unsplash API configuration
 */
const UNSPLASH_CONFIG = {
	baseURL: "https://api.unsplash.com/",
	accessKey: "bf623eb6ee39cc322bb85c8e4575cda12670ee12cbfd85376bac4a022400edde",
};

/**
 * Sidebar component for Easy Attachments.
 *
 * @return {JSX.Element} The sidebar component.
 */
function Sidebar() {
	const [searchTerm, setSearchTerm] = useState("");
	const [debouncedSearchTerm, setDebouncedSearchTerm] = useState("");
	const [isSearching, setIsSearching] = useState(false);
	const [isDownloading, setIsDownloading] = useState(false);
	const [downloadedIds, setDownloadedIds] = useState(new Set());
	const [currentPage, setCurrentPage] = useState(1);
	const [totalPages, setTotalPages] = useState(1);

	// Debounce search term with 500ms delay
	useEffect(() => {
		// Show searching indicator if search term is being typed
		if (searchTerm.length > 0 && searchTerm.length < 3) {
			setIsSearching(false);
			return;
		}

		if (searchTerm.length >= 3) {
			setIsSearching(true);
		}

		const timer = setTimeout(() => {
			setDebouncedSearchTerm(searchTerm);
			setIsSearching(false);
		}, 500);

		return () => {
			clearTimeout(timer);
		};
	}, [searchTerm]);

	// Get current post ID from editor store.
	const currentPostId = useSelect(
		(select) => select(editorStore).getCurrentPostId(),
		[]
	);

	// Get notice dispatch functions.
	const { createSuccessNotice, createErrorNotice } =
		useDispatch("core/notices");

	// Get editor sidebar actions.
	const { openGeneralSidebar } = useDispatch("core/edit-post");

	// Build API URL based on search term and pagination.
	const apiPath = useMemo(() => {
		const shouldSearch = debouncedSearchTerm.length >= 3;
		const endpoint = shouldSearch ? "search/photos" : "photos";
		const params = {
			page: currentPage,
			per_page: 10,
		};

		if (shouldSearch) {
			params.query = debouncedSearchTerm;
		}

		return addQueryArgs(endpoint, params);
	}, [debouncedSearchTerm, currentPage]);

	// Memoize fetch options to prevent infinite loop.
	const fetchOptions = useMemo(
		() => ({
			headers: {
				Authorization: `Client-ID ${UNSPLASH_CONFIG.accessKey}`,
			},
		}),
		[]
	);

	const apiURL = `${UNSPLASH_CONFIG.baseURL}${apiPath}`;

	// Fetch photos from Unsplash.
	const { data, loading, error } = useFetch(apiURL, fetchOptions);

	// Extract photos and pagination info from response.
	const photos = useMemo(() => {
		if (!data) {
			return [];
		}

		// Update total pages from response
		if (data.total_pages) {
			setTotalPages(data.total_pages);
		} else if (data.length > 0) {
			// For non-search endpoints, estimate pages (Unsplash has thousands of photos)
			setTotalPages(100); // Reasonable limit for browsing
		}

		return data.results || data;
	}, [data]);

	/**
	 * Handle image download.
	 *
	 * @param {Object} photo  The photo object from Unsplash.
	 * @param {string} action The action to perform (in-post, featured-image, or media-library).
	 * @param {string} size   The image size to download (raw, full, regular, small, thumb).
	 */
	const handleDownload = useCallback(
		async (photo, action = "media-library", size = "full") => {
			if (isDownloading) {
				return;
			}

			setIsDownloading(photo.id);

			try {
				// Trigger Unsplash download tracking.
				await fetch(`${UNSPLASH_CONFIG.baseURL}photos/${photo.id}/download`, {
					headers: {
						Authorization: `Client-ID ${UNSPLASH_CONFIG.accessKey}`,
					},
				});

				// Get REST URL from config.
				const restUrl =
					window.easyAttachmentsConfig?.restUrl ||
					"/wp-json/easy-attachments/v1/download";

				// Get the appropriate download URL based on size selection
				const downloadUrl = photo.urls[size] || photo.urls.full;

				// Download image to WordPress media library.
				const response = await fetch(restUrl, {
					method: "POST",
					headers: {
						"Content-Type": "application/json",
						"X-WP-Nonce": window.easyAttachmentsConfig?.nonce || "",
					},
					body: JSON.stringify({
						post_id: currentPostId,
						photo,
						download_link: downloadUrl,
						image_size: size,
					}),
				});

				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}

				const result = await response.json();

				if (!result.success || !result.data) {
					throw new Error(result.message || "Download failed");
				}

				// Perform action based on type.
				if (action === "in-post") {
					const { createBlock } = wp.blocks;
					const { insertBlocks } = wp.data.dispatch("core/editor");
					const imageBlock = createBlock("core/image", {
						id: result.data.id,
						url: result.data.url,
						alt: result.data.alt,
						caption: result.data.caption,
					});
					insertBlocks(imageBlock);
				} else if (action === "featured-image") {
					wp.data
						.dispatch("core/editor")
						.editPost({ featured_media: result.data.id });

					// Switch to Page sidebar after setting featured image.
					setTimeout(() => {
						if (openGeneralSidebar) {
							openGeneralSidebar("edit-post/document");
						}
					}, 1000);
				}

				// Add photo ID to downloaded set (persistent).
				setDownloadedIds((prev) => new Set(prev).add(photo.id));

				// Create success message based on action
				let successMessage =
					"Image successfully downloaded to your media library";
				if (action === "in-post") {
					successMessage += " and inserted into the post.";
				} else if (action === "featured-image") {
					successMessage += " and set as the featured image.";
				} else {
					successMessage += ".";
				}

				// Add size info to message
				const sizeLabels = {
					raw: "Raw (Original)",
					full: "Full",
					regular: "Regular",
					small: "Small",
					thumb: "Thumbnail",
				};
				if (sizeLabels[size]) {
					successMessage += ` Size: ${sizeLabels[size]}.`;
				}

				createSuccessNotice(successMessage, { isDismissible: true });
			} catch (err) {
				createErrorNotice(
					err.message || "Failed to download image. Please try again.",
					{ isDismissible: true }
				);
			} finally {
				setIsDownloading(false);
			}
		},
		[
			isDownloading,
			currentPostId,
			createSuccessNotice,
			createErrorNotice,
			openGeneralSidebar,
		]
	);

	/**
	 * Handle page change.
	 *
	 * @param {number} newPage The new page number.
	 */
	const handlePageChange = useCallback(
		(newPage) => {
			if (newPage >= 1 && newPage <= totalPages) {
				setCurrentPage(newPage);
				// Scroll to top of photos list
				const photosContainer = document.querySelector(
					".easy-attachments-sidebar__photos"
				);
				if (photosContainer) {
					photosContainer.scrollTop = 0;
				}
			}
		},
		[totalPages]
	);

	/**
	 * Reset to page 1 when search term changes.
	 */
	const handleSearchChange = useCallback((value) => {
		setSearchTerm(value);
		setCurrentPage(1);
	}, []);

	return (
		<Fragment>
			<PluginSidebarMoreMenuItem target="easy-attachments" icon={<Diaphragm />}>
				Easy Attachments
			</PluginSidebarMoreMenuItem>

			<PluginSidebar
				name="easy-attachments"
				title="Easy Attachments"
				icon={<Diaphragm />}
			>
				<div className="easy-attachments-sidebar">
					<div className="easy-attachments-sidebar__search">
						<SearchControl
							value={searchTerm}
							onChange={handleSearchChange}
							placeholder="Search Unsplash photos..."
						/>
					</div>

					{error && (
						<div className="easy-attachments-sidebar__error">
							<p>Failed to load images. Please try again.</p>
						</div>
					)}

					{loading && (
						<div className="easy-attachments-sidebar__loading">
							<p>Loading images...</p>
						</div>
					)}

					{isSearching && (
						<div className="easy-attachments-sidebar__searching">
							<div className="searching-spinner"></div>
							<p>Searching...</p>
						</div>
					)}

					{!loading &&
						!isSearching &&
						photos.length === 0 &&
						searchTerm &&
						searchTerm.length < 3 && (
							<div className="easy-attachments-sidebar__info">
								<p>Type at least 3 characters to search...</p>
							</div>
						)}

					{!loading &&
						!isSearching &&
						photos.length === 0 &&
						searchTerm &&
						searchTerm.length >= 3 && (
							<div className="easy-attachments-sidebar__no-results">
								<p>No images found. Try a different search term.</p>
							</div>
						)}

					{!isSearching && photos.length > 0 && (
						<>
							<div className="easy-attachments-sidebar__photos">
								{photos.map((photo) => (
									<ImageItem
										key={photo.id}
										photo={photo}
										isDownloading={isDownloading === photo.id}
										isDownloaded={downloadedIds.has(photo.id)}
										onDownload={handleDownload}
									/>
								))}
							</div>

							{totalPages > 1 && (
								<div className="easy-attachments-sidebar__pagination">
									<button
										className="pagination-button pagination-button--prev"
										onClick={() => handlePageChange(currentPage - 1)}
										disabled={currentPage === 1}
										aria-label="Previous page"
									>
										‹
									</button>

									<span className="pagination-info">
										Page {currentPage} of {totalPages}
									</span>

									<button
										className="pagination-button pagination-button--next"
										onClick={() => handlePageChange(currentPage + 1)}
										disabled={currentPage === totalPages}
										aria-label="Next page"
									>
										›
									</button>
								</div>
							)}
						</>
					)}
				</div>
			</PluginSidebar>
		</Fragment>
	);
}

export default Sidebar;
