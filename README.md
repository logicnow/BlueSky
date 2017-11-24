# BlueSky
**NOTE: SolarWinds does not provide technical support for BlueSky.**  Unofficial help may be found on the #solarwinds-msp channel of MacAdmins Slack.

BlueSky establishes and maintains an SSH tunnel initiated by your client’s computer to a BlueSky server. The tunnel allows two connections to come back to the computer from the server: SSH and VNC. The SSH and VNC services on the computer are the ones provided by the Sharing.prefpane.

You use an Admin app to connect via SSH to the BlueSky server and then follow the tunnel back to your client computer. You select which computer by referencing its BlueSky ID as shown in the web admin.

Apps are provided to connect you to remote Terminal (SSH), Screen Sharing (VNC), and File/Folder copying (SCP). You still need to be able to authenticate as a user on the target computer.

Since BlueSky from your client computers is an outgoing connection most SMB networks won’t block it. In enterprise environments, BlueSky can read the proxy configuration in system preferences and send the tunnel through a proxy server.

Read more in the [Wiki](https://github.com/logicnow/BlueSky/wiki)
