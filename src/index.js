/**
 * Easy Attachments Plugin Entry Point
 *
 * @since 1.0.0
 * @package EasyAttachments
 */

import { registerPlugin } from "@wordpress/plugins";
import Sidebar from "./components/Sidebar";

/**
 * Register the Easy Attachments sidebar plugin.
 */
registerPlugin("easy-attachments-sidebar", {
	render: Sidebar,
});
