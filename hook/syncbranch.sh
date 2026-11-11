#!/bin/sh
# SVN post-commit 钩子：提交里出现新的分支目录时，自动把 trunk 的权限同步到该分支
#
# 安装（在目标仓库的 hooks 目录下）：
#   cp /path/to/svnmaia/hook/syncbranch.sh /svn/mysvn/hooks/post-commit
#   chmod +x /svn/mysvn/hooks/post-commit
#
# 手工执行（不接 SVN，直接指定项目）：
#   /path/to/svnmaia/hook/syncbranch.sh --project mysvn
#   /path/to/svnmaia/hook/syncbranch.sh --project mysvn --branch feat1 --force
#   /path/to/svnmaia/hook/syncbranch.sh --repos-path /svn/mysvn --force --enable
#
# 执行方式：
#   有 php 命令行  -> 直接执行 hook/syncbranch_cli.php
#   没有 php 命令行 -> 用 curl 调用 hook/syncbranch_api.php（需该文件能被 web 访问）
#
# 可用环境变量：
#   SVNMAIA_DIR  本系统安装目录，默认 /svnmaia/svnmaia2.2.4
#   PHP_BIN      php 可执行文件；不设置时自动从 PATH 查找，找不到则改用 curl
#   SYNC_MODE    设为 curl 时强制使用 curl 方式
#   SYNC_URL     curl 方式调用的接口地址（默认假定安装目录即站点根目录）
#   SYNC_TOKEN   curl 方式的令牌；不设置时自动用 config.inc 的 SECRET_KEY 计算
#   CURL_BIN     curl 可执行文件，默认从 PATH 查找
#   SVNLOOK_BIN  svnlook 可执行文件，默认从 PATH 查找
#   LOG_FILE     日志文件，默认 /var/log/svn-branch-sync.log
#   SYNC_ALWAYS  设为 1 时，任何提交都全量同步（不只看新增分支）

SVNMAIA_DIR="${SVNMAIA_DIR:-/svnmaia}"
PHP_BIN="${PHP_BIN:-}"
SYNC_MODE="${SYNC_MODE:-}"
SYNC_URL="${SYNC_URL:-http://127.0.0.1/hook/syncbranch_api.php}"
SYNC_TOKEN="${SYNC_TOKEN:-}"
CURL_BIN="${CURL_BIN:-}"
SVNLOOK_BIN="${SVNLOOK_BIN:-}"
LOG_FILE="${LOG_FILE:-/var/log/svn-branch-sync.log}"
SYNC_ALWAYS="${SYNC_ALWAYS:-0}"

CLI="$SVNMAIA_DIR/hook/syncbranch_cli.php"

[ -z "$SVNLOOK_BIN" ] && SVNLOOK_BIN=$(command -v svnlook 2>/dev/null)
[ -z "$SVNLOOK_BIN" ] && SVNLOOK_BIN=/usr/bin/svnlook

log_line()
{
	echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >>"$LOG_FILE" 2>/dev/null
}

# ---------- 选择执行方式：有 php 命令行就用 CLI，否则用 curl 调 web 接口 ----------
# SYNC_MODE=curl 可强制使用 curl（例如本地有 php 但想让钩子走 web 接口）
MODE=""
if [ "$SYNC_MODE" = "curl" ]; then
	MODE="curl"
elif [ -n "$PHP_BIN" ] && [ -x "$PHP_BIN" ]; then
	MODE="php"
else
	FOUND=$(command -v php 2>/dev/null)
	if [ -n "$FOUND" ]; then
		PHP_BIN="$FOUND"
		MODE="php"
	fi
fi
if [ -z "$MODE" ]; then
	[ -z "$CURL_BIN" ] && CURL_BIN=$(command -v curl 2>/dev/null)
	[ -z "$CURL_BIN" ] && CURL_BIN=/usr/bin/curl
	if [ -x "$CURL_BIN" ]; then
		MODE="curl"
	else
		log_line "错误：既没有 php 命令行也没有 curl，无法同步分支权限"
		exit 0
	fi
fi

# 从 config.inc 中读取 SECRET_KEY（curl 方式需要用它计算令牌）
secret_key()
{
	[ -f "$SVNMAIA_DIR/config.inc" ] || return 1
	sed -n "s/.*SECRET_KEY['\"],[[:space:]]*['\"]\([^'\"]*\)['\"].*/\1/p" "$SVNMAIA_DIR/config.inc" | head -n 1
}

# 与 syncbranch_api.php 一致：sha1(SECRET_KEY.'|syncbranch_api|'.repos)
calc_token()
{
	_key=$(secret_key)
	[ -z "$_key" ] && return 1
	if command -v sha1sum >/dev/null 2>&1; then
		printf '%s' "$_key|syncbranch_api|$1" | sha1sum | awk '{print $1}'
	elif command -v shasum >/dev/null 2>&1; then
		printf '%s' "$_key|syncbranch_api|$1" | shasum -a 1 | awk '{print $1}'
	elif command -v openssl >/dev/null 2>&1; then
		printf '%s' "$_key|syncbranch_api|$1" | openssl dgst -sha1 | awk '{print $NF}'
	else
		return 1
	fi
}

usage()
{
	cat <<'TXT'
用法: syncbranch.sh [选项] <项目名>

  --project <名称>     项目名
  --repos-path <路径>  仓库绝对路径，取目录名为项目名
  --branch <分支名>    只同步 branches/<分支名>，可重复指定
  --force              分支已有权限时也用 trunk 覆盖
  --enable             同步成功后开启“自动同步trunk权限”开关
  --no-access          同步后不重新生成 access 权限文件
  --quiet              只输出失败信息
TXT
}

# ---------- php CLI 方式 ----------
run_php()
{
	OUT=$("$PHP_BIN" "$CLI" "$@" 2>&1)
	RC=$?
	echo "$OUT"
	echo "$OUT" >>"$LOG_FILE" 2>/dev/null
	return $RC
}

# ---------- curl 方式 ----------
# 支持与 CLI 相同的选项，转换成接口的 POST 参数
# 需要 urlencode 的内容先写入临时文件，再用 --data-urlencode name@file，避免空格等字符被拆分
run_curl()
{
	_tmpd=$(mktemp -d 2>/dev/null)
	if [ -z "$_tmpd" ] || [ ! -d "$_tmpd" ]; then
		echo "无法创建临时目录，curl 方式同步失败。"
		return 2
	fi

	_repos=""
	_help=""
	_prev=""
	_branchfiles=""
	_flags=""
	_n=0
	for a in "$@"; do
		if [ -n "$_prev" ]; then
			case "$_prev" in
				--branch)
					_n=$((_n + 1))
					printf '%s' "$a" >"$_tmpd/b$_n"
					_branchfiles="$_branchfiles $_tmpd/b$_n"
					;;
				--project) _repos="$a" ;;
				--repos-path) _repos=$(basename "$a") ;;
			esac
			_prev=""
			continue
		fi
		case "$a" in
			--project|--repos-path|--branch) _prev="$a" ;;
			-h|--help) _help=1 ;;
			--force) _flags="$_flags force" ;;
			--enable) _flags="$_flags enable" ;;
			--no-access) _flags="$_flags noaccess" ;;
			--quiet) _flags="$_flags quiet" ;;
			*) [ -z "$_repos" ] && _repos="$a" ;;
		esac
	done

	if [ -n "$_help" ] || [ -z "$_repos" ]; then
		rm -rf "$_tmpd"
		usage
		return 0
	fi

	_tk="$SYNC_TOKEN"
	[ -z "$_tk" ] && _tk=$(calc_token "$_repos")
	if [ -z "$_tk" ]; then
		rm -rf "$_tmpd"
		echo "无法计算同步令牌（请检查 config.inc 中的 SECRET_KEY）。"
		return 2
	fi

	printf '%s' "$_repos" >"$_tmpd/repos"
	printf '%s' "$_tk" >"$_tmpd/token"
	set -- --data-urlencode "repos@$_tmpd/repos" --data-urlencode "token@$_tmpd/token"
	for f in $_branchfiles; do
		set -- "$@" --data-urlencode "branch@$f"
	done
	for fl in $_flags; do
		case "$fl" in
			force) set -- "$@" --data-urlencode force=1 ;;
			enable) set -- "$@" --data-urlencode enable=1 ;;
			noaccess) set -- "$@" --data-urlencode noaccess=1 ;;
			quiet) set -- "$@" --data-urlencode quiet=1 ;;
		esac
	done

	OUT=$("$CURL_BIN" -sS --max-time 300 "$@" "$SYNC_URL" 2>&1)
	RC=$?
	rm -rf "$_tmpd"
	echo "$OUT"
	echo "$OUT" >>"$LOG_FILE" 2>/dev/null
	[ $RC -ne 0 ] && return $RC
	case "$OUT" in
		*RESULT:OK*) return 0 ;;
		*) return 1 ;;
	esac
}

run_sync()
{
	if [ "$MODE" = "php" ]; then
		[ -f "$CLI" ] || { log_line "错误：找不到 $CLI"; return 1; }
		run_php "$@"
	else
		run_curl "$@"
	fi
}

# ---------- 手工调用模式：第2个参数不是版本号 ----------
case "${2:-}" in
	''|*[!0-9]*)
		if [ $# -eq 0 ]; then
			usage
			exit 0
		fi
		run_sync "$@"
		exit 0
		;;
esac

# ---------- post-commit 模式：参数为 (仓库路径, 版本号) ----------
REPOS="$1"
REV="$2"

if [ ! -d "$REPOS" ]; then
	log_line "错误：仓库目录不存在 $REPOS"
	exit 0
fi

if [ "$SYNC_ALWAYS" = "1" ]; then
	set -- --repos-path "$REPOS"
else
	CHANGED=$("$SVNLOOK_BIN" changed -r "$REV" "$REPOS" 2>/dev/null)
	if [ -z "$CHANGED" ]; then
		exit 0
	fi
	# 本次提交新增（A）的顶层分支目录，例如 /branches/feat1 或 /branches/feat1/xxx
	# svnlook changed 每行格式：动作1字符 + 属性标记 + 复制标记 + 路径
	BRANCHES=$(printf '%s\n' "$CHANGED" | awk '
		/^A/ {
			line=$0;
			sub(/^A[ +]+[ \t]+/,"",line);
			sub(/^[ \t]+/,"",line);
			if (substr(line,1,1)!="/") line="/" line;
			if (substr(line,1,10)=="/branches/") {
				rest=substr(line,11);
				n=index(rest,"/");
				if (n>0) rest=substr(rest,1,n-1);
				if (rest!="") print rest;
			}
		}' | sort -u)
	if [ -z "$BRANCHES" ]; then
		exit 0
	fi
	set -- --repos-path "$REPOS"
	for b in $BRANCHES; do
		set -- "$@" --branch "$b"
	done
fi

run_sync "$@" >/dev/null 2>&1
RC=$?
if [ $RC -ne 0 ]; then
	log_line "警告：r$REV 分支权限同步未全部成功（退出码 $RC，方式 $MODE）"
fi

# 钩子失败不应影响提交结果
exit 0
