import Sidebar from './v2/sidebar';

const { registerPlugin } = wp.plugins;

registerPlugin('easy-attachments-sidebar', {
    render: function () {
        return <Sidebar />;
    },
});
