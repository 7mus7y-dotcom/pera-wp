<?php
/**
 * Template Name: Chinese Turkish Citizenship by Investment
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">

<?php
$citizenship_requirements = array(
    '房地产投资金额通常需达到至少 USD 400,000',
    '符合条件的房产一般需持有至少 3 年',
    '购房资金通常需按规定以外币支付并取得 DAB 换汇文件',
    '通常需要取得有效的房产估值报告',
    '主申请人通常可包括配偶及 18 岁以下子女',
    '完整申请通常需要数月，实际时间取决于政府审批',
);
?>

  <section class="hero hero--left hero--citizenship citizenship-hero" id="citizenship-hero">
    <div class="hero__media" aria-hidden="true">
      <?php
      echo wp_get_attachment_image(
          55756,
          'full',
          false,
          array(
              'class'         => 'hero-media',
              'alt'           => '通过伊斯坦布尔房地产申请土耳其投资入籍',
              'fetchpriority' => 'high',
              'loading'       => 'eager',
              'decoding'      => 'async',
          )
      );
      ?>
      <div class="hero-overlay" aria-hidden="true"></div>
    </div>
    <div class="hero-content">
      <div class="citizenship-hero-grid">
        <div class="citizenship-hero-copy">
          <h1>土耳其投资入籍：通过房地产获得土耳其公民身份</h1>
          <p>通过符合条件的 USD 400,000 起土耳其房地产投资，国际投资者可申请土耳其公民身份。Pera Property 为中国及全球投资者提供伊斯坦布尔房产筛选、合规检查、交易协调及入籍申请流程支持。</p>
          <article class="feature-card citizenship-hero-card" aria-label="土耳其投资入籍主要要求">
            <div class="feature-card-header">
              <h2>主要要求</h2>
            </div>
            <div class="feature-card-body">
              <div class="citizenship-requirements-group">
                <h3>投资与房产</h3>
                <ul class="checklist">
                  <?php foreach ( array_slice( $citizenship_requirements, 0, 4 ) as $requirement ) : ?>
                    <li>
                      <svg class="icon icon-tick" aria-hidden="true">
                        <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
                      </svg>
                      <?php echo esc_html( $requirement ); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="citizenship-requirements-group">
                <h3>家庭与流程</h3>
                <ul class="checklist">
                  <?php foreach ( array_slice( $citizenship_requirements, 4 ) as $requirement ) : ?>
                    <li>
                      <svg class="icon icon-tick" aria-hidden="true">
                        <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
                      </svg>
                      <?php echo esc_html( $requirement ); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </article>
          <div class="hero-actions">
            <a href="#citizenship-callback" class="btn btn--solid btn--green">预约中文咨询</a>
            <a href="<?php echo esc_url( home_url( '/turkish-citizenship-properties/?view=cards' ) ); ?>" class="btn btn--solid btn--blue">查看适合入籍的土耳其房产</a>
            <a href="<?php echo esc_url( home_url( '/citizenship-by-investment/' ) ); ?>" class="btn btn--ghost btn--green">View this page in English</a>
          </div>
          <p class="citizenship-trust-strip text-light">自 2016 年起服务国际买家 • 伊斯坦布尔本地团队 • 房产与法律流程清晰协作</p>
        </div>
      </div>
    </div>
  </section>

  <section class="content-panel citizenship-seo-intro">
    <div class="content-panel-box">
      <div class="content-panel-grid--single">
        <header class="section-header section-header--center">
          <h2>通过 Pera Property 规划土耳其投资入籍</h2>
          <p>对许多家庭而言，土耳其投资入籍不仅是第二身份规划，也是一项可持有、可出租、未来可转售的伊斯坦布尔房地产投资。</p>
        </header>
        <div class="citizenship-seo-copy">
          <p>土耳其投资入籍项目允许符合条件的外国投资者，通过满足最低金额及合规要求的投资申请土耳其公民身份。其中，房地产路径是许多国际家庭选择较多的方式，因为它将身份规划与实际资产配置结合在一起。</p>
          <p>Pera Property 协助投资者理解房产路径下的实际流程：包括筛选可能符合入籍要求的伊斯坦布尔房产、安排法律尽调、估值报告、产权过户、银行换汇文件、居留及入籍申请准备，并与持牌土耳其法律合作伙伴协调推进。</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="what-is-turkish-citizenship-by-investment">
    <div class="container">
      <header class="section-header section-header--center">
        <h2>什么是土耳其投资入籍？</h2>
        <p>土耳其投资入籍是土耳其法律框架下的公民身份申请路径。符合条件的外国投资者完成合格投资后，可按规定申请土耳其公民身份。</p>
      </header>
      <div class="feature-grid feature-grid--tablet-3">
        <article class="feature-card"><div class="feature-card-header"><h3>房地产路径</h3></div><div class="feature-card-body"><p>常见路径是购买总额达到 USD 400,000 或以上、并符合估值、产权、付款及登记要求的土耳其房产。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>家庭申请</h3></div><div class="feature-card-body"><p>主申请人通常可将配偶及 18 岁以下子女纳入同一入籍申请，适合有家庭身份规划、教育、居住或长期资产安排需求的投资者。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>以房产为核心的策略</h3></div><div class="feature-card-body"><p>合适的伊斯坦布尔房产不仅应满足入籍合规要求，也应考虑出租需求、区域流动性、未来转售及长期保值能力。</p></div></article>
      </div>
    </div>
  </section>

  <section class="section citizenship-consultancy">
    <div class="container">
      <div class="content-panel-grid--single">
        <header class="section-header section-header--center">
          <h2>伊斯坦布尔一站式投资入籍顾问服务</h2>
          <p>Pera Property 自 2016 年起服务国际买家。我们将伊斯坦布尔本地房产经验与专业法律合作伙伴结合，为投资者提供从房产筛选到入籍申请准备的清晰流程。</p>
        </header>
        <div class="feature-grid feature-grid--tablet-3 citizenship-value-grid">
          <article class="feature-card"><div class="feature-card-body"><p>根据入籍要求、预算、家庭目标和未来使用方式，提供有针对性的房产候选清单。</p></div></article>
          <article class="feature-card"><div class="feature-card-body"><p>协调产权、估值、卖方资格、付款路径及申请文件等关键合规检查。</p></div></article>
          <article class="feature-card"><div class="feature-card-body"><p>由一个可沟通的本地团队跟进项目、律师、银行、估值及产权步骤，减少信息断层。</p></div></article>
        </div>
      </div>
    </div>
  </section>

  <?php get_template_part( 'partials/citizenship-latest-offers' ); ?>

  <section class="section section-soft">
    <div class="container">
      <header class="section-header section-header--center">
        <h2>我们的全流程服务内容</h2>
        <p>从房产选择到申请文件准备，Pera Property 与法律合作伙伴为投资者提供协调式支持。</p>
      </header>
      <div class="feature-grid">
        <article class="feature-card"><div class="feature-card-header"><h3>入籍适用房产筛选</h3></div><div class="feature-card-body"><p>根据当前 USD 400,000 房地产投资门槛及产权登记要求，筛选可能适合投资入籍的伊斯坦布尔房产。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>法律尽调协调</h3></div><div class="feature-card-body"><p>由持牌土耳其律师对产权、抵押、限制、卖方资格及项目文件进行审查，降低交易与申请风险。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>税号、银行及付款路径</h3></div><div class="feature-card-body"><p>协助安排土耳其税号、银行开户、资金路径说明及外币换汇文件等实际步骤。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>购房与产权过户</h3></div><div class="feature-card-body"><p>协助推进认购、合同、估值报告、付款、产权过户及 3 年不得出售限制登记。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>居留与入籍文件</h3></div><div class="feature-card-body"><p>法律合作伙伴准备并提交符合条件家庭成员的居留及入籍申请文件，并跟进政府部门反馈。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>交付后与出租管理</h3></div><div class="feature-card-body"><p>如投资者希望出租或长期持有，我们可协助对接房产管理、租赁及未来转售策略。</p></div></article>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="citizenship-key-facts">
    <div class="container">
      <header class="section-header section-header--center">
        <h2>土耳其投资入籍关键信息</h2>
        <p>以下为房地产路径下投资者通常最关注的核心条件。最终要求应以申请时的法律及主管机关实践为准。</p>
      </header>
      <div class="info-steps">
        <article class="info-step"><div class="info-step-icon"><span class="info-step-number">1</span></div><div class="info-step-body"><h3 class="info-step-title">USD 400,000+ 房地产投资</h3><p class="info-step-text">投资者通常需购买一套或多套符合条件的土耳其房产，总合格价值达到至少 USD 400,000。</p></div></article>
        <article class="info-step"><div class="info-step-icon"><span class="info-step-number">2</span></div><div class="info-step-body"><h3 class="info-step-title">配偶及 18 岁以下子女</h3><p class="info-step-text">主申请人的配偶及 18 岁以下子女通常可以纳入同一申请。成年子女及父母通常需要单独评估其他路径。</p></div></article>
        <article class="info-step"><div class="info-step-icon"><span class="info-step-number">3</span></div><div class="info-step-body"><h3 class="info-step-title">通常数月完成</h3><p class="info-step-text">从购房、居留到入籍审批，完整且合规准备的申请通常需要数月。实际时间取决于文件完整性及政府处理进度。</p></div></article>
        <article class="info-step"><div class="info-step-icon"><span class="info-step-number">4</span></div><div class="info-step-body"><h3 class="info-step-title">土耳其通常允许双重国籍</h3><p class="info-step-text">土耳其通常允许双重国籍，但您是否可以保留原国籍，还需确认原国籍国家或地区的相关法律。</p></div></article>
      </div>
    </div>
  </section>

  <section class="section" id="conditions">
    <div class="container">
      <header class="section-header section-header--center"><h2>土耳其房产投资入籍要求</h2></header>
      <div class="info-steps">
        <article class="info-step"><div class="info-step-body"><h3 class="info-step-title">投资金额</h3><p class="info-step-text">购入房产的合格价值通常需达到至少 USD 400,000，并通过估值、付款及登记文件支持。</p></div></article>
        <article class="info-step"><div class="info-step-body"><h3 class="info-step-title">产权登记</h3><p class="info-step-text">房产需拥有合法产权证（TAPU），并在土耳其土地登记系统中完成相应登记。</p></div></article>
        <article class="info-step"><div class="info-step-body"><h3 class="info-step-title">可组合多套房产</h3><p class="info-step-text">投资者通常可以通过一套或多套符合条件的房产达到最低投资额；是否可用需逐项审查。</p></div></article>
        <article class="info-step"><div class="info-step-body"><h3 class="info-step-title">3 年持有限制</h3><p class="info-step-text">符合入籍用途的房产通常需在产权登记中注明至少 3 年不得出售的限制。</p></div></article>
        <article class="info-step"><div class="info-step-body"><h3 class="info-step-title">估值报告</h3><p class="info-step-text">通常需要由具备资质的估值机构出具估值报告，以确认房产价值满足相关要求。</p></div></article>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="who-can-apply">
    <div class="container">
      <header class="section-header section-header--center"><h2>哪些家庭成员可以纳入同一申请？</h2><p>土耳其投资入籍通常允许主申请人将核心家庭成员纳入同一申请文件。</p></header>
      <div class="feature-grid">
        <article class="feature-card"><div class="feature-card-header"><h3>主申请人</h3></div><div class="feature-card-body"><p>完成符合条件房地产投资的主要投资人。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>配偶</h3></div><div class="feature-card-body"><p>主申请人的配偶通常可作为家庭成员加入同一入籍申请，无需额外投资。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>18 岁以下子女</h3></div><div class="feature-card-body"><p>18 岁以下子女通常可以纳入同一申请，前提是亲属关系文件完整并符合要求。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>特殊情况成年子女</h3></div><div class="feature-card-body"><p>有正式医学证明的特殊需求成年子女，是否可作为被抚养人纳入申请需由律师个案评估。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>父母通常不纳入</h3></div><div class="feature-card-body"><p>父母通常不包括在主申请人的投资入籍文件中，但可另行评估居留或其他适合路径。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>统一协调文件</h3></div><div class="feature-card-body"><p>律师会根据家庭结构准备文件清单，协调翻译、公证、认证及提交时间。</p></div></article>
      </div>
    </div>
  </section>

  <section class="section" id="citizenship-benefits">
    <div class="container">
      <header class="section-header section-header--center"><h2>土耳其投资入籍的主要优势</h2></header>
      <div class="feature-grid">
        <article class="feature-card"><div class="feature-card-header"><h3>较快的身份路径</h3></div><div class="feature-card-body"><p>在文件完整、流程顺利的情况下，土耳其投资入籍通常属于较快的公民身份申请路径之一。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>以房地产为基础</h3></div><div class="feature-card-body"><p>投资者持有的是实际房产资产，而不仅是单纯行政申请成本。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>家庭身份规划</h3></div><div class="feature-card-body"><p>配偶和 18 岁以下子女通常可纳入同一申请，适合以家庭为单位做长期规划。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>通常无居住要求</h3></div><div class="feature-card-body"><p>房地产投资入籍路径通常不要求申请人在获批前后长期居住在土耳其。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>伊斯坦布尔资产配置</h3></div><div class="feature-card-body"><p>合适的伊斯坦布尔房产可兼顾出租收益、未来转售和长期资产配置。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>3 年后可重新规划</h3></div><div class="feature-card-body"><p>完成强制持有期后，投资者通常可考虑出售、继续出租或调整房产组合。</p></div></article>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="citizenship-callback">
    <div class="container">
      <div class="enquiry-cta">
        <?php if ( isset( $_GET['enquiry'] ) && $_GET['enquiry'] === 'ok' ) : ?>
          <div class="alert alert-success">感谢您的咨询，我们已收到您的信息。团队会尽快与您联系。</div>
        <?php endif; ?>
        <header class="enquiry-cta-header">
          <h2>获取适合投资入籍的伊斯坦布尔房产清单</h2>
          <p>请填写预算、家庭成员和时间计划，Pera Property 团队将为您的土耳其入籍申请准备合适的伊斯坦布尔房产建议。</p>
        </header>
        <section id="citizenship-form" class="citizenship-form-section">
          <?php if ( isset( $_GET['enquiry'] ) ) : ?>
            <?php $status = sanitize_text_field( wp_unslash( $_GET['enquiry'] ) ); $is_success = ( $status === 'ok' ); ?>
            <div class="citizenship-alert citizenship-alert--<?php echo $is_success ? 'success' : 'error'; ?>">
              <?php if ( $is_success ) : ?><p>感谢您的咨询。我们的团队会尽快与您联系。</p><?php else : ?><p>抱歉，您的信息未能发送。请重试或直接联系我们。</p><?php endif; ?>
            </div>
          <?php endif; ?>
          <form class="enquiry-cta-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>">
            <?php wp_nonce_field( 'pera_citizenship_enquiry', 'pera_citizenship_nonce' ); ?>
            <input type="hidden" name="pera_citizenship_action" value="1">
            <input type="hidden" name="form_start" value="<?php echo esc_attr( time() ); ?>">
            <input type="hidden" name="citizenship_redirect_path" value="/zh-citizenship-by-investment/">
            <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
              <label for="citizenship-company">Company</label>
              <input type="text" id="citizenship-company" name="citizenship_company" value="" tabindex="-1" autocomplete="off">
            </div>
            <div class="enquiry-cta-grid">
              <div class="enquiry-cta-column">
                <h3 class="enquiry-cta-subtitle">必填信息</h3>
                <div class="cta-fieldset">
                  <label class="cta-field"><span class="cta-label">姓名</span><input type="text" name="name" class="cta-control" placeholder="您的姓名" required></label>
                  <label class="cta-field"><span class="cta-label">电话</span><input type="tel" name="phone" class="cta-control" placeholder="+86 / +90 ..." required></label>
                  <label class="cta-field"><span class="cta-label">邮箱</span><input type="email" name="email" class="cta-control" placeholder="you@example.com" required></label>
                </div>
                <div class="cta-fieldset cta-fieldset--inline">
                  <span class="cta-label cta-label--muted">偏好的联系方式</span>
                  <div class="cta-options">
                    <label class="cta-checkbox"><input type="checkbox" name="contact_method[]" value="phone"><span>电话</span></label>
                    <label class="cta-checkbox"><input type="checkbox" name="contact_method[]" value="email"><span>邮箱</span></label>
                    <label class="cta-checkbox"><input type="checkbox" name="contact_method[]" value="whatsapp"><span>WhatsApp</span></label>
                  </div>
                </div>
              </div>
              <div class="enquiry-cta-column">
                <h3 class="enquiry-cta-subtitle">补充信息</h3>
                <div class="cta-fieldset">
                  <label class="cta-field"><span class="cta-label">咨询类型</span><select name="enquiry_type" class="cta-control"><option value="general">一般咨询</option><option value="citizenship-only">仅咨询入籍</option><option value="citizenship-property">入籍与房产投资</option><option value="consultation">预约视频咨询</option></select></label>
                  <label class="cta-field"><span class="cta-label">家庭成员</span><input type="text" name="family" class="cta-control" placeholder="申请人数、子女年龄等"></label>
                  <label class="cta-field"><span class="cta-label">问题或备注</span><textarea name="message" rows="3" class="cta-control" placeholder="请简单说明您的预算、时间计划或关注区域。"></textarea></label>
                </div>
              </div>
            </div>
            <div class="enquiry-cta-footer">
              <label class="cta-checkbox"><input type="checkbox" name="policy" required><span>我同意 <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>" target="_blank" rel="noopener">隐私政策</a> 条款。</span></label>
              <?php $turnstile_site_key = defined( 'PERA_TURNSTILE_SITE_KEY' ) ? sanitize_text_field( (string) PERA_TURNSTILE_SITE_KEY ) : ''; ?>
              <?php if ( $turnstile_site_key !== '' ) : ?>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>"></div>
              <?php endif; ?>
              <button type="submit" class="btn btn--ghost btn--green">发送房产清单请求</button>
            </div>
          </form>
        </section>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <header class="section-header section-header--center"><h2>为什么选择 Pera Property？</h2><p>Pera Property 是位于伊斯坦布尔的房地产顾问团队，专注协助国际买家理解土耳其房产市场和投资入籍房产路径。</p></header>
      <div class="feature-grid">
        <article class="feature-card"><div class="feature-card-header"><h3>熟悉入籍房产要求</h3></div><div class="feature-card-body"><p>我们优先考虑房产是否适合入籍申请，而不仅是价格是否达到门槛。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>透明费用</h3></div><div class="feature-card-body"><p>提前说明房产交易、法律服务和行政流程中的主要费用，避免流程中出现不清晰的成本。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>本地与国际团队</h3></div><div class="feature-card-body"><p>伊斯坦布尔本地顾问与经验丰富的移民法律合作伙伴协同工作。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>端到端项目协调</h3></div><div class="feature-card-body"><p>协助协调开发商、估值师、银行、律师和土地登记步骤，使申请保持清晰进度。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>伊斯坦布尔市场洞察</h3></div><div class="feature-card-body"><p>关注区域长期价值、租赁需求和 3 年持有期后的转售流动性。</p></div></article>
        <article class="feature-card"><div class="feature-card-header"><h3>长期服务关系</h3></div><div class="feature-card-body"><p>获批后，如您需要出租、管理、出售或再投资，我们可继续提供本地支持。</p></div></article>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="citizenship-timeline">
    <div class="container">
      <header class="section-header section-header--center"><h2>土耳其投资入籍流程时间线</h2><p>以下为一般性参考流程。实际时间取决于房产、文件准备、政府处理和个人家庭情况。</p></header>
      <ol class="timeline">
        <li class="timeline-step"><div class="timeline-side"><span class="timeline-duration">3–5 天</span><span class="timeline-phase">初步规划</span></div><div class="timeline-marker"><span class="timeline-number">1</span></div><div class="timeline-body"><h3>咨询与方案确认</h3><p>了解您的家庭结构、预算、时间计划和房产偏好，说明当前项目要求及申请逻辑。</p></div></li>
        <li class="timeline-step"><div class="timeline-side"><span class="timeline-duration">2–4 周</span><span class="timeline-phase">文件准备</span></div><div class="timeline-marker"><span class="timeline-number">2</span></div><div class="timeline-body"><h3>准备个人及家庭文件</h3><p>律师提供文件清单，包括护照、出生证明、婚姻文件、照片、授权书、翻译及认证等。</p></div></li>
        <li class="timeline-step"><div class="timeline-side"><span class="timeline-duration">1–2 周</span><span class="timeline-phase">选房</span></div><div class="timeline-marker"><span class="timeline-number">3</span></div><div class="timeline-body"><h3>筛选并预订房产</h3><p>根据入籍要求和投资目标筛选伊斯坦布尔项目，可安排实地或远程看房。</p></div></li>
        <li class="timeline-step"><div class="timeline-side"><span class="timeline-duration">2–4 周</span><span class="timeline-phase">成交</span></div><div class="timeline-marker"><span class="timeline-number">4</span></div><div class="timeline-body"><h3>付款、估值与产权过户</h3><p>完成付款路径、估值报告、产权登记及 3 年不得出售限制登记等关键步骤。</p></div></li>
        <li class="timeline-step"><div class="timeline-side"><span class="timeline-duration">4–8 周+</span><span class="timeline-phase">提交</span></div><div class="timeline-marker"><span class="timeline-number">5</span></div><div class="timeline-body"><h3>居留与入籍申请</h3><p>法律合作伙伴提交居留及入籍文件，并跟进政府部门的审查和补件要求。</p></div></li>
        <li class="timeline-step"><div class="timeline-side"><span class="timeline-duration">通常数月</span><span class="timeline-phase">获批</span></div><div class="timeline-marker"><span class="timeline-number">6</span></div><div class="timeline-body"><h3>获得土耳其身份证与护照</h3><p>获批后，成功申请人可按程序领取土耳其身份证和护照，具体安排由律师确认。</p></div></li>
      </ol>
    </div>
  </section>

  <section class="section" id="fast-track-process">
    <div class="container">
      <header class="section-header"><h2>投资者居留与入籍加速流程</h2><p>土耳其投资入籍实践中，部分投资者可通过更集中的方式完成居留申请、生物识别和入籍文件提交。是否适用取决于申请时的政策、预约安排和个人文件情况。</p></header>
      <h3>常规流程</h3><ul class="checklist"><li>先完成投资者居留申请及相关步骤</li><li>随后准备并提交入籍申请文件</li><li>可能涉及多次预约和较长等待时间</li></ul>
      <h3>可能的加速安排</h3><ul class="checklist"><li>部分案件可在一次到访中集中完成居留、生物识别和入籍提交相关步骤</li><li>到达土耳其后完成必要的身份核验和生物识别流程</li><li>文件完整时，可减少多次往返和预约等待</li><li>具体可行性需由律师根据最新安排确认</li></ul>
    </div>
  </section>

  <section class="section section-soft" id="citizenship-documents">
    <div class="container">
      <header class="section-header section-header--center"><h2>通常需要准备的文件</h2><p>具体文件清单会因国籍、婚姻状态、家庭结构和申请时要求而不同。以下为常见项目。</p></header>
      <div class="docs-list">
        <details class="doc-item" open><summary><span class="doc-title">有效护照</span><span class="doc-icon" aria-hidden="true"></span></summary><div class="doc-body"><p>每位申请人的有效护照或认可旅行证件。</p></div></details>
        <details class="doc-item"><summary><span class="doc-title">婚姻及婚姻状态文件</span><span class="doc-icon" aria-hidden="true"></span></summary><div class="doc-body"><p>已婚需提供结婚证；离婚需提供离婚文件；未婚可能需要单身证明。</p></div></details>
        <details class="doc-item"><summary><span class="doc-title">出生证明</span><span class="doc-icon" aria-hidden="true"></span></summary><div class="doc-body"><p>通常每位申请人需提供出生证明。如无法取得，律师会说明可接受的替代文件。</p></div></details>
        <details class="doc-item"><summary><span class="doc-title">配偶及子女资料</span><span class="doc-icon" aria-hidden="true"></span></summary><div class="doc-body"><p>纳入申请的配偶及子女通常需要护照、出生证明及亲属关系文件。</p></div></details>
        <details class="doc-item"><summary><span class="doc-title">土耳其税号</span><span class="doc-icon" aria-hidden="true"></span></summary><div class="doc-body"><p>主申请人通常需要取得土耳其税号，用于银行、购房及行政流程。</p></div></details>
        <details class="doc-item"><summary><span class="doc-title">房产估值报告</span><span class="doc-icon" aria-hidden="true"></span></summary><div class="doc-body"><p>符合要求的估值报告用于确认房产价值是否达到入籍门槛。</p></div></details>
        <details class="doc-item"><summary><span class="doc-title">产权证 TAPU</span><span class="doc-icon" aria-hidden="true"></span></summary><div class="doc-body"><p>符合条件房产的产权证，并按要求登记 3 年不得出售限制。</p></div></details>
        <details class="doc-item"><summary><span class="doc-title">投资确认文件</span><span class="doc-icon" aria-hidden="true"></span></summary><div class="doc-body"><p>由土地登记或相关主管机关确认投资符合入籍要求的文件。</p></div></details>
        <details class="doc-item"><summary><span class="doc-title">认证、翻译及合法化</span><span class="doc-icon" aria-hidden="true"></span></summary><div class="doc-body"><p>出生、婚姻、离婚、单身等文件通常需要认证或海牙认证，并翻译为土耳其语。</p></div></details>
      </div>
    </div>
  </section>

  <section class="section" id="citizenship-compliance-checks">
    <div class="container">
      <header class="section-header section-header--center"><h2>购房前必须完成的法律与合规检查</h2><p>用于土耳其投资入籍的房产，不应只看价格是否达到 USD 400,000。产权、估值、付款、卖方资格和登记限制都可能影响申请。</p></header>
      <div class="content-panel-box citizenship-advisory-panel">
        <div class="citizenship-advisory-copy"><p>Pera Property 会与持牌土耳其法律合作伙伴协调，在投资者付款或签署重要文件前，对房产和交易结构进行必要审查。</p><p>常见检查包括：产权证审查、抵押或限制查询、卖方及项目资格、估值报告、DAB 外币换汇文件、土地登记中的 3 年持有限制，以及合格证明相关步骤。</p><p>如果您正在比较 <a href="<?php echo esc_url( home_url( '/property/' ) ); ?>">伊斯坦布尔房产</a>，我们的团队可以说明哪些房产在入籍方面需要进一步法律确认。</p></div>
        <div class="content-note" role="note" aria-label="法律服务免责声明"><strong>Pera Property 不是律师事务所。</strong> 入籍申请由持牌土耳其法律合作伙伴处理。Pera Property 会协助核查所推荐房产的入籍适用性，并建议投资者在估值、产权和资格未确认前不要支付不可退还款项。</div>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="is-turkish-citizenship-right-for-you">
    <div class="container">
      <header class="section-header section-header--center"><h2>土耳其投资入籍适合您吗？</h2><p>土耳其投资入籍适合部分家庭和投资者，但并非适合所有目标。是否合适取决于您的家庭需求、资金安排、文件准备、持有期限和对房产投资的接受度。</p></header>
      <div class="feature-grid citizenship-fit-grid"><article class="feature-card citizenship-fit-card"><div class="feature-card-header"><h3>较适合</h3></div><div class="feature-card-body"><ul class="checklist"><li>希望规划第二公民身份的家庭</li><li>希望持有实际房地产资产的投资者</li><li>关注伊斯坦布尔长期流动性的买家</li><li>能够接受至少 3 年持有期的申请人</li><li>希望选择相对较快身份路径的投资者</li></ul></div></article><article class="feature-card citizenship-fit-card"><div class="feature-card-header"><h3>可能不适合</h3></div><div class="feature-card-body"><ul class="checklist checklist--cross"><li>主要目标是免签美国或申根区的申请人</li><li>希望购房后立即出售的投资者</li><li>不愿准备银行、法律及资金来源文件的申请人</li><li>只追求最低价格，而忽视合规、地段和未来转售风险的买家</li></ul></div></article></div>
    </div>
  </section>

  <section class="section" id="turkish-citizenship-investment-routes">
    <div class="container">
      <header class="section-header section-header--center"><h2>土耳其投资入籍路径比较</h2><p>房地产路径是许多国际投资者常用的方式，但并非唯一方式。具体选择应根据投资目标、文件情况、时间计划和资产偏好决定。</p></header>
      <div class="citizenship-table-wrap" role="region" aria-label="土耳其投资入籍路径比较" tabindex="0"><table class="citizenship-route-table"><thead><tr><th scope="col">路径</th><th scope="col">常见最低投资额</th><th scope="col">适用场景</th><th scope="col">备注</th></tr></thead><tbody><tr><th scope="row">房地产</th><td>USD 400,000</td><td>常见选择</td><td>符合条件房产通常需持有至少 3 年</td></tr><tr><th scope="row">银行存款</th><td>USD 500,000</td><td>资本保全</td><td>银行要求、锁定规则和文件流程不同</td></tr><tr><th scope="row">政府债券</th><td>USD 500,000</td><td>被动投资</td><td>对房地产市场表现的直接敞口较低</td></tr><tr><th scope="row">商业或就业路径</th><td>视情况而定</td><td>运营型投资者</td><td>通常更复杂，需要个案评估</td></tr></tbody></table></div>
      <p class="citizenship-route-note">投资门槛和申请实践可能变化。做出投资决定前，应由持牌土耳其法律顾问确认最新要求。您也可以 <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">联系 Pera Property</a> 讨论您的目标。</p>
    </div>
  </section>

  <?php
  get_template_part( 'parts/citizenship-guide-posts', null, array(
    'category_slug'      => 'chinese-buyers',
    'section_aria_label' => '中国买家指南文章',
    'eyebrow'            => '中国买家指南',
    'heading'            => '继续阅读我们的土耳其房产与入籍中文指南',
    'intro'              => '这些指南为中国买家介绍土耳其购房、投资入籍、伊斯坦布尔区域选择、估值、付款文件和产权流程中的关键问题。',
    'slider_id'          => 'zh-citizenship-guide-posts-slider',
    'prev_aria_label'    => '上一篇中国买家指南',
    'next_aria_label'    => '下一篇中国买家指南',
  ) );
  ?>

  <?php get_template_part( 'partials/faq', 'zh-citizenship' ); ?>

  <section class="section cta" id="citizenship-enquiry">
    <div class="container">
      <h2>准备了解土耳其投资入籍方案？</h2>
      <p>请告诉我们您的预算、家庭成员和时间计划。Pera Property 顾问将帮助您了解适合的伊斯坦布尔房产选择和下一步流程。</p>
      <div class="hero-actions">
        <a href="#citizenship-callback" class="btn btn--solid btn--green">联系入籍顾问</a>
        <a href="https://wa.me/905320639978?text=Hello%20Pera%20Property%2C%20I%20would%20like%20Chinese%20guidance%20about%20Turkish%20citizenship%20by%20investment." class="btn btn--ghost btn--green" data-whatsapp="1" data-whatsapp-type="citizenship_cta" data-track-channel="whatsapp" data-track-intent="high" data-track-source="template" data-track-context="zh_citizenship_page" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click">WhatsApp 咨询</a>
        <a href="<?php echo esc_url( home_url( '/citizenship-by-investment/' ) ); ?>" class="btn btn--ghost btn--green">View this page in English</a>
      </div>
    </div>
  </section>

</main>

<?php
get_footer();
