# lSubConfig
一个简单的订阅重写器, 只适用于猫.

支持以下功能:
1. 自动获取单个或多个订阅并缓存;
2. 合并及提取其中节点, 并整合自己的基础规则;
3. 对于部分客户端 bug 及功能问题的缓解, 如:
   * Stash 不兼容 xtls-rprx-vision.
   * Verge 监听端口不为 * 或 127.0.0.1 时系统代理不生效 (https://github.com/clash-verge-rev/clash-verge-rev/issues/344).
