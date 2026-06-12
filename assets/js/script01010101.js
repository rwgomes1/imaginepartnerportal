document.addEventListener('DOMContentLoaded', () => {
    const contentArea = document.getElementById('content-area');
    const rangeSlider = document.getElementById('range-slider');
    const pointer = document.getElementById('pointer');
    const pointerValue = document.getElementById('pointer-value');
    const sliderPanels = document.querySelectorAll('.slider-panel');
    const bubbleButtons = document.querySelectorAll('.bubble');

    // Content for all the bubbles
    const contentData = {
        "bubble-1": { heading: "Review referral/reseller agreements for desired ImagineSoftware solution", body: "Reviewing referral and reseller agreements with ImagineOne® and ImaginePay® ensures partners fully understand the terms and benefits of collaboration. These agreements outline responsibilities, compensation models, customer relationship management, and support resources, providing a clear framework for mutual success. Whether referring clients or reselling solutions under your brand, these agreements establish transparency and efficiency for a rewarding partnership." },
        "bubble-2": { heading: "Sign NDAs and clarify expectations.", body: "Sign NDAs and clarify expectations to ensure confidentiality and alignment in our partnership. This step establishes trust, protects sensitive information, and sets clear guidelines for responsibilities, deliverables, and mutual goals, creating a strong foundation for a successful collaboration." },
        "bubble-3": { heading: "Finalize contracts with partners.", body: "Finalize contracts with partners to solidify the terms of collaboration and ensure mutual understanding. This step formalizes the agreement, outlining roles, responsibilities, and expectations, while providing a clear framework for a successful and productive partnership." },
        "bubble-4": { heading: "Complete contracts and NDAs.", body: "Complete contracts and NDAs to formalize the partnership and ensure confidentiality. This process establishes clear terms, protects sensitive information, and aligns expectations, creating a solid foundation for a successful and collaborative relationship." },
        "bubble-5": { heading: "Obtain Partner Qualification Guideline approval.", body: "Obtain Partner Qualification Guideline approval to ensure all partners meet the necessary standards and align with our goals. This step verifies compliance, fosters mutual success, and maintains the integrity of our partnerships." },
        "bubble-6": { heading: "Review technical integration documentation, including APIs and web services.", body: "Review technical integration documentation, including APIs and web services, to ensure seamless connectivity and compatibility. This step provides partners with the necessary technical details to effectively integrate with our platforms, enabling efficient workflows and a streamlined user experience." },
        "bubble-7": {
            heading: "IT VSAQ Questionaire and kickoff meeting.",
            body: "Obtain IT Vendor Security Assessment Questionnaire (VSAQ) approval and conduct a comprehensive kickoff meeting to ensure partners meet the highest standards of cybersecurity compliance and operational readiness. The VSAQ process verifies adherence to stringent security protocols, safeguarding sensitive data and maintaining system integrity. The kickoff meeting aligns stakeholders on project goals, timelines, and technical requirements, while addressing security concerns, clarifying expectations, and discussing ongoing measures to remain cyber compliant and safe. Together, these steps build trust, mitigate risks, and establish a secure foundation for successful collaboration."
        },
        "bubble-8": {
            heading: "Introduction to the Partner Portal.",
            body: "The Partner Portal is a comprehensive resource designed to support your partnership by providing easy access to essential information and tools. It serves as a centralized hub for marketing materials, integration documentation, training resources, and support guides. The portal is tailored to help partners effectively manage their collaboration, stay informed, and access the resources needed to achieve mutual success."
        },
        "bubble-9": {
            heading: "Finalization of Solution Design.",
            body: "Mutual teams will finalize the design and workflow of the proposed integration and obtain written sign-off.  This validates that project scope is finalized prior to technical integration and keeps both teams accountable to the mutual goal."
        },
        "bubble-10": {
            heading: "Development build and testing phase.",
            body: "The development build and testing phase is a critical step in ensuring the successful implementation of our solutions. During this phase, the integration is developed based on technical specifications, followed by rigorous testing to validate functionality, performance, and compatibility. This process identifies and resolves potential issues, ensuring a seamless and reliable solution that meets both technical and business requirements."
        },
        "bubble-11": {
            heading: "Full integration ready for deployment.",
            body: "The full integration is finalized and ready for deployment, marking the completion of development and testing. At this stage, all systems are fully configured, validated for compatibility, and optimized for performance. This ensures a seamless transition to live operations, enabling partners to leverage the integrated solution effectively and confidently support their business objectives."
        },
        "bubble-12": {
            heading: "Testing with pilot clients.",
            body: "Testing with pilot clients ensures that the integration functions as intended in real-world scenarios before full deployment. This phase involves selecting a controlled group of clients to validate performance, identify any remaining issues, and gather feedback. By addressing potential challenges and refining the solution based on pilot results, this step ensures a smooth and successful rollout for all users."
        },
        "bubble-13": {
            heading: "Demo and sales training videos provided by ImagineSoftware.",
            body: "ImagineSoftware provides comprehensive demo and sales training videos to equip partners with the knowledge and tools needed to effectively showcase and sell our solutions. These resources offer step-by-step guidance on platform features, benefits, and use cases, empowering partners to confidently communicate value propositions and address client needs. With these videos, partners can enhance their understanding, improve client engagement, and drive successful outcomes."
        },
        "bubble-14": {
            heading: "Obtain <strong>optional</strong> certifications (ICP, ICEE).",
            body: "Partners can obtain <strong>optional</strong> certifications, such as ICP and ICEE, to enhance their expertise and credibility. These certifications demonstrate a strong understanding of ImagineSoftware solutions, equipping partners with advanced skills to better support their clients. Achieving these certifications also strengthens the partnership by showcasing a commitment to excellence and elevating the partner&#39;s standing within the ImagineSoftware ecosystem."
        },
        "bubble-15": {
            heading: "Transition to Partner Concierge Manager.",
            body: "The transition to a Partner Concierge Manager marks the handoff to a dedicated partner liaison who will serve as the primary point of contact. This ensures ongoing support, effective communication, and alignment with strategic goals. The Relationship Officer will work closely with partners to foster collaboration, address needs, and maximize the value of the partnership over time."
        },
        "bubble-16": {
            heading: "Maintain successful partnership with contract renewals.",
            body: "Maintaining a successful partnership includes regular engagement and timely contract renewals to ensure continuity and alignment. This process strengthens the collaboration by reaffirming commitments, evaluating performance, and adapting to evolving needs. By prioritizing open communication and proactive renewal discussions, the partnership remains productive and mutually beneficial."
        },
        "bubble-17": {
            heading: "Add additional VAPs and integrations as needed.",
            body: "Expand your capabilities by adding additional VAPs and integrations as needed to meet evolving business requirements. This flexibility allows partners to enhance their offerings, streamline workflows, and provide greater value to clients. By leveraging new integrations, the partnership stays adaptable and aligned with industry advancements and client needs."
        },
        "bubble-18": {
            heading: "Expand client base and enrich existing opportunities.",
            body: "Expand your client base and enrich existing opportunities by leveraging the tools, resources, and support available through the partnership. This approach focuses on building new relationships while strengthening current ones, driving growth, and maximizing the value delivered to clients. By continuously identifying and pursuing opportunities, partners can achieve sustained success and long-term impact."
        }
    };

    // Function to update the visible slider panel and pointer
    function updateSliderPanel(value) {
        const adjustedValue = Math.floor(value / 30) + 1; // Convert slider value to 1-6
        sliderPanels.forEach((panel, index) => {
            panel.style.display = index === adjustedValue - 1 ? 'block' : 'none';
        });
        pointerValue.textContent = adjustedValue; // Display adjusted value in the circle
        pointer.style.left = `${(value / 150) * 100}%`;

        // Automatically populate content from the first bubble of the current slider
        const firstBubbleId = `bubble-${(adjustedValue - 1) * 3 + 1}`;
        if (contentData[firstBubbleId]) {
            const { heading, body } = contentData[firstBubbleId];
            contentArea.innerHTML = `<h1>${heading}</h1><p>${body}</p>`;
        } else {
            contentArea.innerHTML = "<p>No content available for this selection.</p>";
        }
    }

    // Initialize slider
    updateSliderPanel(parseInt(rangeSlider.value, 10));

    // Event listener for range slider
    rangeSlider.addEventListener('input', (event) => {
        const value = parseInt(event.target.value, 10);
        updateSliderPanel(value);
    });

    // Event listeners for bubble buttons
    bubbleButtons.forEach(bubble => {
        bubble.addEventListener('click', () => {
            const target = bubble.getAttribute('data-target');
            if (contentData[target]) {
                const { heading, body } = contentData[target];
                contentArea.innerHTML = `<h1>${heading}</h1><p>${body}</p>`;
            } else {
                contentArea.innerHTML = "<p>No content available for this selection.</p>";
            }
        });
    });

    // Event listener for partner form submission
    const partnerForm = document.getElementById('partner-form');
    partnerForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(partnerForm);
        const data = Object.fromEntries(formData.entries());
        console.log('Form submitted:', data);
        alert('Thank you for becoming a partner!');
        partnerForm.reset();
    });
});
