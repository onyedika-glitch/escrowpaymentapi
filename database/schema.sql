
CREATE TABLE public.kyc_profiles (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    legal_full_name character varying(255) NOT NULL,
    country character(2) NOT NULL,
    government_id_type character varying(30) NOT NULL,
    government_id_number character varying(255) NOT NULL,
    date_of_birth date,
    status character varying(20) DEFAULT 'Verified'::character varying NOT NULL,
    verified_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

CREATE SEQUENCE public.kyc_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.kyc_profiles_id_seq OWNED BY public.kyc_profiles.id;

CREATE TABLE public.ledger_entries (
    id bigint NOT NULL,
    wallet_id bigint NOT NULL,
    type character varying(40) NOT NULL,
    direction character varying(6) NOT NULL,
    amount_cents bigint NOT NULL,
    balance_after_cents bigint NOT NULL,
    escrow_after_cents bigint NOT NULL,
    project_id bigint,
    milestone_id bigint,
    reference character varying(255),
    description character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE SEQUENCE public.ledger_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.ledger_entries_id_seq OWNED BY public.ledger_entries.id;

CREATE TABLE public.milestones (
    id bigint NOT NULL,
    project_id bigint NOT NULL,
    freelancer_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    amount_cents bigint NOT NULL,
    due_date date NOT NULL,
    status character varying(20) DEFAULT 'Created'::character varying NOT NULL,
    funded_at timestamp(0) without time zone,
    released_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

CREATE SEQUENCE public.milestones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.milestones_id_seq OWNED BY public.milestones.id;

CREATE TABLE public.project_freelancer (
    project_id bigint NOT NULL,
    freelancer_id bigint NOT NULL,
    assigned_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE public.projects (
    id bigint NOT NULL,
    client_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    status character varying(20) DEFAULT 'Open'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

CREATE SEQUENCE public.projects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.projects_id_seq OWNED BY public.projects.id;

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    role character varying(20) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;

CREATE TABLE public.wallets (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    balance_cents bigint DEFAULT '0'::bigint NOT NULL,
    escrow_cents bigint DEFAULT '0'::bigint NOT NULL,
    currency character(3) DEFAULT 'USD'::bpchar NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

CREATE SEQUENCE public.wallets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.wallets_id_seq OWNED BY public.wallets.id;

CREATE TABLE public.webhook_deliveries (
    id bigint NOT NULL,
    event character varying(40) NOT NULL,
    url character varying(255) NOT NULL,
    payload json NOT NULL,
    response_status smallint,
    success boolean DEFAULT false NOT NULL,
    error text,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE SEQUENCE public.webhook_deliveries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.webhook_deliveries_id_seq OWNED BY public.webhook_deliveries.id;

ALTER TABLE ONLY public.kyc_profiles ALTER COLUMN id SET DEFAULT nextval('public.kyc_profiles_id_seq'::regclass);

ALTER TABLE ONLY public.ledger_entries ALTER COLUMN id SET DEFAULT nextval('public.ledger_entries_id_seq'::regclass);

ALTER TABLE ONLY public.milestones ALTER COLUMN id SET DEFAULT nextval('public.milestones_id_seq'::regclass);

ALTER TABLE ONLY public.projects ALTER COLUMN id SET DEFAULT nextval('public.projects_id_seq'::regclass);

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);

ALTER TABLE ONLY public.wallets ALTER COLUMN id SET DEFAULT nextval('public.wallets_id_seq'::regclass);

ALTER TABLE ONLY public.webhook_deliveries ALTER COLUMN id SET DEFAULT nextval('public.webhook_deliveries_id_seq'::regclass);

ALTER TABLE ONLY public.kyc_profiles
    ADD CONSTRAINT kyc_profiles_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.kyc_profiles
    ADD CONSTRAINT kyc_profiles_user_id_unique UNIQUE (user_id);

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.milestones
    ADD CONSTRAINT milestones_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.project_freelancer
    ADD CONSTRAINT project_freelancer_pkey PRIMARY KEY (project_id, freelancer_id);

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT wallets_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT wallets_user_id_unique UNIQUE (user_id);

ALTER TABLE ONLY public.webhook_deliveries
    ADD CONSTRAINT webhook_deliveries_pkey PRIMARY KEY (id);

CREATE INDEX ledger_entries_wallet_id_created_at_index ON public.ledger_entries USING btree (wallet_id, created_at);

CREATE INDEX milestones_project_id_status_index ON public.milestones USING btree (project_id, status);

ALTER TABLE ONLY public.kyc_profiles
    ADD CONSTRAINT kyc_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_milestone_id_foreign FOREIGN KEY (milestone_id) REFERENCES public.milestones(id);

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id);

ALTER TABLE ONLY public.ledger_entries
    ADD CONSTRAINT ledger_entries_wallet_id_foreign FOREIGN KEY (wallet_id) REFERENCES public.wallets(id);

ALTER TABLE ONLY public.milestones
    ADD CONSTRAINT milestones_freelancer_id_foreign FOREIGN KEY (freelancer_id) REFERENCES public.users(id);

ALTER TABLE ONLY public.milestones
    ADD CONSTRAINT milestones_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.project_freelancer
    ADD CONSTRAINT project_freelancer_freelancer_id_foreign FOREIGN KEY (freelancer_id) REFERENCES public.users(id);

ALTER TABLE ONLY public.project_freelancer
    ADD CONSTRAINT project_freelancer_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.users(id);

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT wallets_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;

