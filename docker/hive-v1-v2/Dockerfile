# https://github.com/big-data-europe/docker-hive
FROM bde2020/hadoop-base:2.0.0-hadoop2.7.4-java8

# Allow buildtime config of HIVE_VERSION
ARG HIVE_VERSION
ENV HIVE_VERSION=${HIVE_VERSION:-2.3.6}

ENV HIVE_HOME /opt/hive
ENV YARN_HOME $HIVE_HOME
ENV HIVE_CONF_DIR $HIVE_HOME/conf
ENV PATH $HIVE_HOME/bin:$PATH
ENV HADOOP_HOME /opt/hadoop-$HADOOP_VERSION
ENV HADOOP_MAPRED_HOME=$HADOOP_HOME

WORKDIR /opt

# Install utils
# Use archive.debian.org for Jessie (EOL)
RUN echo "deb http://archive.debian.org/debian/ jessie main" > /etc/apt/sources.list && \
    echo "deb http://archive.debian.org/debian-security/ jessie/updates main" >> /etc/apt/sources.list && \
    apt-get update && \
    apt-get install -y --force-yes wget procps && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install Postgresql JDBC connector
RUN mkdir -p $HIVE_HOME/lib && \
    wget https://jdbc.postgresql.org/download/postgresql-9.4.1212.jar -O $HIVE_HOME/lib/postgresql-jdbc.jar

# Install Hive (last install step, previous steps are same for all versions)
RUN	wget https://archive.apache.org/dist/hive/hive-$HIVE_VERSION/apache-hive-$HIVE_VERSION-bin.tar.gz && \
	tar --keep-old-files -xzvf apache-hive-$HIVE_VERSION-bin.tar.gz && \
	cp -r apache-hive-$HIVE_VERSION-bin/* $HIVE_HOME/ && \
	rm -r apache-hive-$HIVE_VERSION-bin && \
	rm apache-hive-$HIVE_VERSION-bin.tar.gz

# Hive Configuration
ADD conf/* $HIVE_CONF_DIR/

COPY startup.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/startup.sh

COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Init
COPY custom-init.sh  /usr/local/bin/custom-init.sh
RUN chmod +x /usr/local/bin/custom-init.sh
CMD custom-init.sh

# Ports
EXPOSE 10000
EXPOSE 10002

# Entrypoint
ENTRYPOINT ["entrypoint.sh"]
